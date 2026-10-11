<?php

namespace App\Services;

use App\Support\DnsResolver;
use App\Support\PublicAddress;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * The server-side fetch behind the admin HLS downloader (HlsDownloaderController::proxy).
 * The browser falls back to it when a CDN blocks cross-origin reads or checks the
 * Referer, and it fetches whatever a playlist names, so a hostile playlist picks the
 * URLs. Hence: http(s) only, every address the host resolves to must be public, the
 * connection is pinned to the address that was checked, and each redirect hop goes
 * through the same checks.
 */
class HlsProxyService
{
    public const MAX_REDIRECTS = 5;

    public const MAX_URL_LENGTH = 4096;

    public const MAX_CUSTOM_HEADERS = 32;

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * Framing and hop-by-hop headers the proxy sets itself. Dropped rather than refused:
     * a header block pasted from DevTools carries most of them.
     */
    private const DROPPED_HEADERS = [
        'accept-encoding', 'connection', 'content-length', 'expect', 'host', 'keep-alive',
        'proxy-authorization', 'proxy-connection', 'range', 'te', 'trailer', 'transfer-encoding', 'upgrade',
    ];

    /** Never replayed to a different host after a redirect, matching browsers and curl. */
    private const CREDENTIAL_HEADERS = ['authorization', 'cookie'];

    private const FALLBACK_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    public function __construct(private DnsResolver $dns) {}

    /**
     * The request headers to send upstream.
     *
     * @param  array<array-key, mixed>  $custom  what the admin set in the tool (Referer, Cookie, ...)
     * @param  string|null  $range  the browser's Range header; Mediabunny always sends `bytes=N-`
     * @param  string|null  $userAgent  the admin's own browser, so the CDN sees the client it expects
     * @return array<string, string>
     *
     * @throws HlsProxyException
     */
    public function headers(array $custom, ?string $range, ?string $userAgent): array
    {
        if (count($custom) > self::MAX_CUSTOM_HEADERS) {
            throw new HlsProxyException('Too many custom headers.');
        }

        $headers = [
            'Accept' => '*/*',
            // Nothing to decode keeps the relayed Content-Length truthful.
            'Accept-Encoding' => 'identity',
            'User-Agent' => $userAgent ?: self::FALLBACK_USER_AGENT,
        ];

        foreach ($custom as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                throw new HlsProxyException('Custom headers must be name/value pairs.');
            }

            $name = trim($name);

            if ($name === '' || str_starts_with($name, ':') || in_array(strtolower($name), self::DROPPED_HEADERS, true)) {
                continue;
            }

            if (! preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) || preg_match('/[\r\n\x00]/', $value) || strlen($value) > 8192) {
                throw new HlsProxyException('The "'.substr($name, 0, 60).'" header cannot be sent as written.');
            }

            // A custom header replaces a default of the same name, whatever its casing.
            foreach (array_keys($headers) as $existing) {
                if (strcasecmp($existing, $name) === 0) {
                    unset($headers[$existing]);
                }
            }

            $headers[$name] = trim($value);
        }

        if ($range !== null && preg_match('/^bytes=\d+-\d*$/', $range)) {
            $headers['Range'] = $range;
        }

        return $headers;
    }

    /**
     * Fetch $url, following up to MAX_REDIRECTS redirects. Returns the final response
     * with its body unread (it streams) and the URL that served it.
     *
     * @param  array<string, string>  $headers  from headers()
     * @return array{response: Response, url: string}
     *
     * @throws HlsProxyException
     */
    public function open(string $url, array $headers): array
    {
        $firstHost = null;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->target($url);
            $firstHost ??= $target['host'];

            if ($target['host'] !== $firstHost) {
                $headers = array_filter(
                    $headers,
                    fn ($name) => ! in_array(strtolower((string) $name), self::CREDENTIAL_HEADERS, true),
                    ARRAY_FILTER_USE_KEY,
                );
            }

            $response = $this->send($target, $headers);
            $location = $response->header('Location');

            if ($location === '' || ! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return ['response' => $response, 'url' => $url];
            }

            $response->toPsrResponse()->getBody()->close();

            try {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
            } catch (InvalidArgumentException) {
                throw new HlsProxyException('The upstream server sent a malformed redirect.', 502);
            }
        }

        throw new HlsProxyException('Too many redirects.', 502);
    }

    /**
     * @return array{url: string, scheme: string, host: string, port: int, ip: string}
     *
     * @throws HlsProxyException
     */
    private function target(string $url): array
    {
        // The same refusal as PriceComparison::url(): PHP's URL parser and a browser's
        // disagree about backslashes, whitespace and control characters, and nothing
        // legitimate needs them, so refuse rather than try to out-parse either one.
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\\\\\s\x00-\x1F\x7F<>"]/', $url)) {
            throw new HlsProxyException('That is not a valid URL.');
        }

        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';

        if (! is_array($parts) || ! isset(self::DEFAULT_PORTS[$scheme]) || ($parts['host'] ?? '') === '') {
            throw new HlsProxyException('Only http:// and https:// URLs can be fetched.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new HlsProxyException('URLs with a username or password are not supported; send credentials as a header instead.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        return [
            'url' => $url,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $parts['port'] ?? self::DEFAULT_PORTS[$scheme],
            'ip' => $this->publicAddress($host),
        ];
    }

    /** @throws HlsProxyException */
    private function publicAddress(string $host): string
    {
        $literal = trim($host, '[]');
        $addresses = filter_var($literal, FILTER_VALIDATE_IP) ? [$literal] : $this->dns->resolve($host);

        if ($addresses === []) {
            throw new HlsProxyException("Could not resolve {$host}.", 502);
        }

        // Every record, not only the one connected to: a name that mixes public and
        // private answers is not one to talk to at all.
        foreach ($addresses as $address) {
            if (! PublicAddress::isPublic($address)) {
                throw new HlsProxyException("{$host} points at a private or reserved address, which the proxy never fetches.");
            }
        }

        return $addresses[0];
    }

    /**
     * @param  array{url: string, scheme: string, host: string, port: int, ip: string}  $target
     * @param  array<string, string>  $headers
     *
     * @throws HlsProxyException
     */
    private function send(array $target, array $headers): Response
    {
        $name = trim($target['host'], '[]');
        $hostHeader = $target['host'].($target['port'] === self::DEFAULT_PORTS[$target['scheme']] ? '' : ':'.$target['port']);

        // Connect to the address that was checked, never to what a second DNS lookup
        // returns (rebinding).
        if (ini_get('allow_url_fopen')) {
            // Streamed, which Guzzle runs on its StreamHandler. It must stream: Mediabunny
            // reads with open-ended `Range: bytes=N-` requests and aborts once it has
            // enough, so a buffering proxy would download whole files nobody asked for.
            // StreamHandler cannot pin a resolution, so the URL carries the IP while the
            // Host header and TLS peer name carry the hostname: virtual hosting, SNI and
            // certificate verification behave exactly as they would unpinned.
            $ip = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];
            $url = (string) (new Uri($target['url']))->withHost($ip)->withFragment('');
            $options = ['stream' => true, 'stream_context' => ['ssl' => ['peer_name' => $name]]];
        } else {
            // Without allow_url_fopen Guzzle falls back to cURL: buffered, but it pins natively.
            $ip = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];
            $url = $target['url'];
            $options = ['curl' => [CURLOPT_RESOLVE => ["{$name}:{$target['port']}:{$ip}"]]];
        }

        try {
            return Http::withHeaders([...$headers, 'Host' => $hostHeader])
                ->withOptions([...$options, 'allow_redirects' => false, 'read_timeout' => 30])
                ->connectTimeout(10)
                ->timeout(60)
                ->send('GET', $url);
        } catch (ConnectionException) {
            throw new HlsProxyException("Could not connect to {$target['host']}.", 502);
        }
    }
}
