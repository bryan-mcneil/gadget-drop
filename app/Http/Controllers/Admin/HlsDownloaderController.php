<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HlsProxyException;
use App\Services\HlsProxyService;
use Illuminate\Http\Client\Response as UpstreamResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only HLS (.m3u8) to MP4 downloader. The browser does the work
 * (resources/js/lib/hls-download.js runs Mediabunny); proxy() is its fallback
 * transport for CDNs that block cross-origin reads or check the Referer.
 */
class HlsDownloaderController extends Controller
{
    private const CHUNK_BYTES = 262144;

    /** Consecutive empty reads tolerated before a stalled upstream is abandoned. */
    private const MAX_EMPTY_READS = 100;

    /** Sent with every proxy answer, relayed or refused. */
    private const BASE_HEADERS = [
        'Cache-Control' => 'no-store, private',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'Cross-Origin-Resource-Policy' => 'same-origin',
    ];

    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/Tools/HlsDownloader');
    }

    public function proxy(Request $request, HlsProxyService $proxy): Response
    {
        // Only the tool's own fetch() sends X-Hls-Proxy, and a page on another site cannot
        // add a custom header without a CORS preflight this route never answers. So a link
        // or an <img> elsewhere cannot ride the admin session to use the server as a fetcher.
        $site = $request->header('Sec-Fetch-Site');
        abort_unless($request->header('X-Hls-Proxy') === '1' && in_array($site, [null, 'same-origin'], true), 403);

        $url = $request->query('url');

        try {
            $headers = $proxy->headers($this->customHeaders($request), $request->header('Range'), $request->userAgent());
            ['response' => $upstream, 'url' => $finalUrl] = $proxy->open(is_string($url) ? $url : '', $headers);
        } catch (HlsProxyException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status, [...self::BASE_HEADERS, 'X-Hls-Proxy-Error' => '1']);
        }

        $body = $upstream->toPsrResponse()->getBody();

        if ($upstream->status() >= 400) {
            // The status is all the client needs; an upstream error page is not worth relaying.
            $body->close();

            return response('', $upstream->status(), $this->relayHeaders($upstream, $finalUrl));
        }

        return response()->stream(
            fn () => $this->pump($body),
            $upstream->status(),
            [...$this->relayHeaders($upstream, $finalUrl), ...$this->bodyHeaders($upstream)],
        );
    }

    /**
     * Headers the admin set in the tool (Referer, Cookie, ...). They travel base64 JSON in a
     * request header rather than the query string, which keeps cookies out of access logs.
     *
     * @return array<array-key, mixed>
     *
     * @throws HlsProxyException
     */
    private function customHeaders(Request $request): array
    {
        $encoded = (string) $request->header('X-Hls-Headers', '');

        if ($encoded === '') {
            return [];
        }

        $json = base64_decode($encoded, true);
        $decoded = $json === false ? null : json_decode($json, true);

        if (! is_array($decoded)) {
            throw new HlsProxyException('The custom headers could not be read.');
        }

        return $decoded;
    }

    /** @return array<string, string> */
    private function relayHeaders(UpstreamResponse $upstream, string $finalUrl): array
    {
        $headers = [
            ...self::BASE_HEADERS,
            // Third-party bytes served from the admin origin: no browser may ever render them,
            // whatever type the upstream claimed. The claimed type travels in X-Hls-Upstream-Type.
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            // Where the bytes really came from, so relative playlist paths resolve past redirects.
            'X-Hls-Final-Url' => $this->asciiUrl($finalUrl),
        ];

        $type = strtolower(trim(explode(';', $upstream->header('Content-Type'))[0]));

        if (preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#', $type)) {
            $headers['X-Hls-Upstream-Type'] = $type;
        }

        return $headers;
    }

    /** @return array<string, string> */
    private function bodyHeaders(UpstreamResponse $upstream): array
    {
        $headers = [];

        foreach (['Content-Range', 'Accept-Ranges', 'Content-Encoding'] as $name) {
            if ($upstream->header($name) !== '') {
                $headers[$name] = $upstream->header($name);
            }
        }

        // Guzzle drops Content-Length when it decodes a compressed body. Only relay a length
        // that still describes the bytes being sent.
        if ($upstream->header('Content-Length') !== '' && $upstream->header('Content-Encoding') === '') {
            $headers['Content-Length'] = $upstream->header('Content-Length');
        }

        return $headers;
    }

    private function pump(StreamInterface $body): void
    {
        $emptyReads = 0;

        while (! $body->eof() && $emptyReads < self::MAX_EMPTY_READS) {
            $chunk = $body->read(self::CHUNK_BYTES);

            if ($chunk === '') {
                // A blocking read comes back empty at EOF, on read_timeout, or when the
                // de-chunking filter consumed only framing bytes. Only the last is worth retrying.
                if ($body->getMetadata('timed_out')) {
                    break;
                }

                $emptyReads++;

                continue;
            }

            $emptyReads = 0;
            echo $chunk;

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();

            // Mediabunny aborts a read once it has enough; stop pulling from upstream too.
            if (connection_aborted()) {
                break;
            }
        }

        $body->close();
    }

    /** Header values must be ASCII; percent-encode the rest so the client gets the URL intact. */
    private function asciiUrl(string $url): string
    {
        return (string) preg_replace_callback('/[^\x21-\x7E]/', fn (array $match) => rawurlencode($match[0]), $url);
    }
}
