<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\HlsProxyService;
use App\Support\DnsResolver;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as UpstreamRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The admin HLS downloader: its page, and HlsProxyService, the server-side fetch the
 * browser falls back to. DNS answers are fixed per test and preventStrayRequests()
 * keeps the suite off the network.
 */
class HlsDownloaderTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = '/admin/tools/hls-downloader';

    private const PROXY = '/admin/tools/hls-downloader/proxy';

    /** @var array<string, list<string>> hostname => the addresses DNS answers with */
    private array $dns = [
        'cdn.example.com' => ['93.184.216.34'],
        'media.example.org' => ['104.16.0.1'],
    ];

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->mock(DnsResolver::class)
            ->shouldReceive('resolve')
            ->andReturnUsing(fn (string $host) => $this->dns[$host] ?? []);
    }

    // ---------------------------------------------------------------- the page

    public function test_the_page_requires_login(): void
    {
        $this->get(self::PAGE)->assertRedirect('/login');
    }

    public function test_the_page_renders_for_the_admin_and_is_kept_out_of_search(): void
    {
        $this->actingAs($this->admin())
            ->get(self::PAGE)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Tools/HlsDownloader'));
    }

    public function test_the_tool_stays_off_the_public_tools_index_and_the_sitemap(): void
    {
        $this->assertArrayNotHasKey('hls-downloader', config('tools'));
        $this->get('/tools')->assertOk()->assertDontSee('hls-downloader');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('hls-downloader');
    }

    // ------------------------------------------------------------ proxy gates

    public function test_the_proxy_requires_login(): void
    {
        $this->withHeaders(['X-Hls-Proxy' => '1'])
            ->get($this->proxyUrl('https://cdn.example.com/master.m3u8'))
            ->assertRedirect('/login');

        Http::assertNothingSent();
    }

    public function test_the_proxy_only_answers_the_tools_own_requests(): void
    {
        $this->actingAs($this->admin())
            ->get($this->proxyUrl('https://cdn.example.com/master.m3u8'))
            ->assertForbidden();

        $this->withHeaders(['X-Hls-Proxy' => '1', 'Sec-Fetch-Site' => 'cross-site'])
            ->get($this->proxyUrl('https://cdn.example.com/master.m3u8'))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function unfetchableUrls(): array
    {
        return [
            'empty' => [''],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://cdn.example.com/master.m3u8'],
            'javascript scheme' => ['javascript:alert(1)'],
            'no host' => ['https:///master.m3u8'],
            'embedded credentials' => ['https://user:secret@cdn.example.com/master.m3u8'],
            'backslash host confusion' => ['https://cdn.example.com\\@media.example.org/master.m3u8'],
            'raw whitespace' => ['https://cdn.example.com/my video.m3u8'],
        ];
    }

    #[DataProvider('unfetchableUrls')]
    public function test_the_proxy_refuses_urls_it_cannot_safely_fetch(string $url): void
    {
        $this->proxy($url)
            ->assertStatus(422)
            ->assertHeader('X-Hls-Proxy-Error', '1')
            ->assertJsonStructure(['message']);

        Http::assertNothingSent();
    }

    /** @return array<string, array{string, array<string, list<string>>}> */
    public static function privateDestinations(): array
    {
        return [
            'hostname on loopback' => ['https://evil.example/master.m3u8', ['evil.example' => ['127.0.0.1']]],
            'hostname on a private network' => ['https://evil.example/master.m3u8', ['evil.example' => ['10.0.0.5']]],
            'one private record among public ones' => ['https://evil.example/master.m3u8', ['evil.example' => ['93.184.216.34', '192.168.1.10']]],
            'IPv6 loopback record' => ['https://evil.example/master.m3u8', ['evil.example' => ['::1']]],
            'cloud metadata literal' => ['http://169.254.169.254/latest/meta-data/', []],
            'IPv6 loopback literal' => ['http://[::1]/master.m3u8', []],
            'IPv4-mapped loopback literal' => ['http://[::ffff:127.0.0.1]/master.m3u8', []],
        ];
    }

    /** @param  array<string, list<string>>  $dns */
    #[DataProvider('privateDestinations')]
    public function test_the_proxy_never_fetches_private_or_reserved_addresses(string $url, array $dns): void
    {
        $this->dns = [...$this->dns, ...$dns];

        $this->proxy($url)->assertStatus(422)->assertHeader('X-Hls-Proxy-Error', '1');

        Http::assertNothingSent();
    }

    public function test_an_unresolvable_host_is_a_bad_gateway(): void
    {
        $this->proxy('https://nowhere.example/master.m3u8')
            ->assertStatus(502)
            ->assertHeader('X-Hls-Proxy-Error', '1');

        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- the relay

    public function test_the_upstream_body_streams_from_the_address_that_was_checked(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('segment-bytes', 206, [
            'Content-Type' => 'video/mp2t',
            'Content-Range' => 'bytes 0-12/13',
            'Content-Length' => '13',
            'Accept-Ranges' => 'bytes',
        ])]);

        $response = $this->proxy('https://cdn.example.com/hls/seg-1.ts?token=abc', ['Range' => 'bytes=0-']);

        $response->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-12/13')
            ->assertHeader('Content-Length', '13')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('X-Hls-Final-Url', 'https://cdn.example.com/hls/seg-1.ts?token=abc')
            ->assertHeader('X-Hls-Upstream-Type', 'video/mp2t')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame('segment-bytes', $response->streamedContent());

        Http::assertSent(fn (UpstreamRequest $request) => $request->url() === 'https://93.184.216.34/hls/seg-1.ts?token=abc'
            && $request->header('Host') === ['cdn.example.com']
            && $request->header('Range') === ['bytes=0-']
            && $request->header('Accept-Encoding') === ['identity']);
    }

    public function test_relayed_bytes_can_never_render_in_the_admin_origin(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('<script>alert(document.cookie)</script>', 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ])]);

        $response = $this->proxy('https://cdn.example.com/watch.html');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Disposition', 'attachment')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Hls-Upstream-Type', 'text/html');
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_admins_headers_are_forwarded_but_the_proxys_own_cannot_be_overridden(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('#EXTM3U', 200)]);

        $this->proxy('https://cdn.example.com/master.m3u8', ['X-Hls-Headers' => $this->encodeHeaders([
            'Referer' => 'https://player.example.net/watch/42',
            'Origin' => 'https://player.example.net',
            'Cookie' => 'session=abc123',
            'User-Agent' => 'CustomAgent/1.0',
            'Host' => 'internal.example',
            'Accept-Encoding' => 'gzip, br',
            'Connection' => 'keep-alive',
            ':authority' => 'internal.example',
        ])])->assertOk();

        Http::assertSent(fn (UpstreamRequest $request) => $request->header('Referer') === ['https://player.example.net/watch/42']
            && $request->header('Origin') === ['https://player.example.net']
            && $request->header('Cookie') === ['session=abc123']
            && $request->header('User-Agent') === ['CustomAgent/1.0']
            && $request->header('Host') === ['cdn.example.com']
            && $request->header('Accept-Encoding') === ['identity']
            && ! $request->hasHeader('Connection'));
    }

    public function test_the_admins_own_browser_user_agent_is_the_default(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('#EXTM3U', 200)]);

        $this->proxy('https://cdn.example.com/master.m3u8', ['User-Agent' => 'Mozilla/5.0 (AdminBrowser)'])->assertOk();

        Http::assertSent(fn (UpstreamRequest $request) => $request->header('User-Agent') === ['Mozilla/5.0 (AdminBrowser)']);
    }

    /** @return array<string, array{string}> */
    public static function malformedHeaderBlobs(): array
    {
        return [
            'not base64' => ['%%%not-base64%%%'],
            'not JSON' => [base64_encode('Referer: https://example.com')],
            'a list, not an object' => [base64_encode('["Referer"]')],
            'an invalid header name' => [base64_encode('{"Bad Header":"x"}')],
            'a value with a line break' => [base64_encode('{"X-Test":"a\r\nInjected: yes"}')],
        ];
    }

    #[DataProvider('malformedHeaderBlobs')]
    public function test_malformed_custom_headers_are_refused(string $blob): void
    {
        $this->proxy('https://cdn.example.com/master.m3u8', ['X-Hls-Headers' => $blob])
            ->assertStatus(422)
            ->assertHeader('X-Hls-Proxy-Error', '1');

        Http::assertNothingSent();
    }

    // ----------------------------------------------------------------- redirects

    public function test_a_cross_host_redirect_is_followed_rechecked_and_stripped_of_credentials(): void
    {
        Http::fake([
            '93.184.216.34/*' => Http::response('', 302, ['Location' => 'https://media.example.org/abc/index.m3u8']),
            '104.16.0.1/*' => Http::response("#EXTM3U\n", 200),
        ]);

        $response = $this->proxy('https://cdn.example.com/watch/42.m3u8', ['X-Hls-Headers' => $this->encodeHeaders([
            'Referer' => 'https://player.example.net/watch/42',
            'Cookie' => 'session=abc123',
            'Authorization' => 'Bearer secret',
        ])]);

        $response->assertOk()->assertHeader('X-Hls-Final-Url', 'https://media.example.org/abc/index.m3u8');
        $this->assertSame("#EXTM3U\n", $response->streamedContent());

        Http::assertSent(fn (UpstreamRequest $request) => str_starts_with($request->url(), 'https://104.16.0.1/')
            && $request->header('Host') === ['media.example.org']
            && $request->header('Referer') === ['https://player.example.net/watch/42']
            && ! $request->hasHeader('Cookie')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_a_same_host_relative_redirect_keeps_the_credentials(): void
    {
        Http::fake([
            '93.184.216.34/watch/*' => Http::response('', 301, ['Location' => '/hls/42/index.m3u8']),
            '93.184.216.34/hls/*' => Http::response("#EXTM3U\n", 200),
        ]);

        $this->proxy('https://cdn.example.com/watch/42.m3u8', ['X-Hls-Headers' => $this->encodeHeaders(['Cookie' => 'session=abc123'])])
            ->assertOk()
            ->assertHeader('X-Hls-Final-Url', 'https://cdn.example.com/hls/42/index.m3u8');

        Http::assertSent(fn (UpstreamRequest $request) => $request->url() === 'https://93.184.216.34/hls/42/index.m3u8'
            && $request->header('Cookie') === ['session=abc123']);
    }

    public function test_a_redirect_into_a_private_network_is_refused(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/'])]);

        $this->proxy('https://cdn.example.com/master.m3u8')
            ->assertStatus(422)
            ->assertHeader('X-Hls-Proxy-Error', '1');

        Http::assertSentCount(1);
    }

    public function test_a_redirect_loop_gives_up(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('', 302, ['Location' => 'https://cdn.example.com/master.m3u8'])]);

        $this->proxy('https://cdn.example.com/master.m3u8')
            ->assertStatus(502)
            ->assertHeader('X-Hls-Proxy-Error', '1');

        Http::assertSentCount(HlsProxyService::MAX_REDIRECTS + 1);
    }

    // ------------------------------------------------------------------ failures

    public function test_upstream_errors_keep_their_status_but_not_their_body(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('<h1>Hotlinking not allowed</h1>', 403, [
            'Content-Type' => 'text/html',
            'Content-Length' => '31',
        ])]);

        $response = $this->proxy('https://cdn.example.com/master.m3u8');

        $response->assertForbidden()
            ->assertHeaderMissing('X-Hls-Proxy-Error')
            ->assertHeaderMissing('Content-Length');
        $this->assertSame('', $response->getContent());
    }

    public function test_an_unreachable_host_is_a_bad_gateway(): void
    {
        Http::fake(fn (UpstreamRequest $request) => throw new ConnectException('Connection refused', $request->toPsrRequest()));

        $this->proxy('https://cdn.example.com/master.m3u8')
            ->assertStatus(502)
            ->assertHeader('X-Hls-Proxy-Error', '1');
    }

    // ------------------------------------------------------------------- helpers

    private function admin(): User
    {
        return $this->admin ??= User::factory()->create();
    }

    private function proxyUrl(string $url): string
    {
        return self::PROXY.'?url='.rawurlencode($url);
    }

    /** @param  array<string, string>  $headers */
    private function proxy(string $url, array $headers = []): TestResponse
    {
        return $this->actingAs($this->admin())
            ->withHeaders(['X-Hls-Proxy' => '1', ...$headers])
            ->get($this->proxyUrl($url));
    }

    /** @param  array<string, string>  $headers */
    private function encodeHeaders(array $headers): string
    {
        return base64_encode(json_encode($headers, JSON_THROW_ON_ERROR));
    }
}
