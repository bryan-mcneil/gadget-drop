// HLS (.m3u8) → MP4 engine for the admin HLS Downloader (Pages/Admin/Tools/HlsDownloader.jsx).
// Imported dynamically, so Mediabunny only loads when the tool is used.
//
// The browser reads the stream itself whenever the CDN allows it. When a host blocks
// cross-origin reads, or refuses the request, the transport retries that host through
// the admin proxy (HlsDownloaderController::proxy), which can also send the Referer,
// cookies and user agent that fetch() is not allowed to set.
import {
    BufferTarget,
    EncodedAudioPacketSource,
    EncodedPacketSink,
    EncodedVideoPacketSource,
    HLS_FORMATS,
    Input,
    Mp4OutputFormat,
    Output,
    StreamTarget,
    UrlSource,
    desc,
} from 'mediabunny';

/** A failure with a message written for the admin; never retried. */
export class ToolError extends Error {
    constructor(message) {
        super(message);
        this.name = 'ToolError';
    }
}

/** What a download's `done` promise rejects with after cancel(). */
export class DownloadCanceledError extends Error {
    constructor() {
        super('Download canceled.');
        this.name = 'DownloadCanceledError';
    }
}

// How much of a pasted URL is read to tell a playlist from a web page.
const SNIFF_BYTES = 5 * 1024 * 1024;

const NULL_BODY_STATUSES = new Set([101, 103, 204, 205, 304]);

// DRM keys (FairPlay, Widevine, PlayReady) come from a license server, not the playlist,
// and working around DRM is out of scope. Plain AES-128 with a key URI is ordinary HLS
// that every player decrypts, Mediabunny included.
const DRM_PATTERNS = [
    /#EXT-X-(?:SESSION-)?KEY:[^\r\n]*METHOD=SAMPLE-AES/i,
    /#EXT-X-(?:SESSION-)?KEY:[^\r\n]*KEYFORMAT="(?!identity")/i,
    /#EXT-X-(?:SESSION-)?KEY:[^\r\n]*URI="skd:/i,
];

const DRM_MESSAGE = 'This stream is DRM-protected (FairPlay, Widevine or PlayReady). Its keys come from a '
    + 'license server rather than the playlist, and this tool does not work around DRM.';

export const isDrmProtected = (playlist) => DRM_PATTERNS.some((pattern) => pattern.test(playlist));

/** Mediabunny's retry hook: a few quick retries for network hiccups, none for refusals. */
const retryDelay = (attempts, error) => {
    if (error instanceof ToolError || error?.name === 'AbortError') {
        return null;
    }

    return attempts <= 3 ? 2 ** (attempts - 1) : null;
};

/** "Name: value" per line. A header block copied from DevTools works as-is. */
export function parseHeaderLines(text = '') {
    const headers = {};

    for (const line of text.split(/\r?\n/)) {
        // HTTP/2 pseudo-headers (":authority: …") start with the separator itself.
        const separator = line.indexOf(':', line.startsWith(':') ? 1 : 0);
        const name = separator > 0 ? line.slice(0, separator).trim() : '';
        const value = separator > 0 ? line.slice(separator + 1).trim() : '';

        if (name && value) {
            headers[name] = value;
        }
    }

    return headers;
}

/** The headers the proxy sends upstream: Referer (plus the Origin a player's fetch would send) and the extras. */
export function buildRequestHeaders(referer = '', extra = '') {
    const headers = {};
    const trimmed = referer.trim();

    if (trimmed) {
        headers.Referer = trimmed;

        try {
            const { origin } = new URL(trimmed);

            if (origin !== 'null') {
                headers.Origin = origin;
            }
        } catch {
            // Not a URL; send the Referer as typed.
        }
    }

    return { ...headers, ...parseHeaderLines(extra) };
}

function encodeHeaders(headers) {
    if (Object.keys(headers).length === 0) {
        return null;
    }

    let binary = '';
    new TextEncoder().encode(JSON.stringify(headers)).forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary);
}

/**
 * The fetch() Mediabunny's UrlSource uses (`fetchFn`) for every playlist, key and segment.
 * Settings are read per request, so a changed Referer applies to the next request. Which
 * route works is remembered per origin.
 *
 * @param {{ proxyUrl: string, getSettings: () => { mode: 'auto'|'proxy'|'direct', referer: string, extraHeaders: string },
 *           onRoute?: (origin: string, route: 'direct'|'proxy') => void, onBytes?: (count: number) => void }} options
 */
export function createTransport({ proxyUrl, getSettings, onRoute = () => {}, onBytes = () => {} }) {
    const routes = new Map();

    async function viaProxy(url, init, encodedHeaders) {
        const headers = { 'X-Hls-Proxy': '1' };
        const range = new Headers(init.headers ?? {}).get('Range');

        if (range) {
            headers.Range = range;
        }

        if (encodedHeaders) {
            headers['X-Hls-Headers'] = encodedHeaders;
        }

        const response = await fetch(`${proxyUrl}?url=${encodeURIComponent(url)}`, {
            headers,
            signal: init.signal,
            credentials: 'same-origin',
        });

        // The proxy never redirects; an expired session does (to the login page).
        if (response.redirected) {
            throw new ToolError('Your admin session has expired. Reload this page and log in again.');
        }

        if (response.headers.get('X-Hls-Proxy-Error')) {
            const payload = await response.json().catch(() => ({}));
            throw new ToolError(payload.message || `The server could not fetch ${url}.`);
        }

        return response;
    }

    function wrap(response, requestedUrl, viaServer) {
        const body = response.body && !NULL_BODY_STATUSES.has(response.status)
            ? response.body.pipeThrough(new TransformStream({
                transform(chunk, controller) {
                    onBytes(chunk.byteLength);
                    controller.enqueue(chunk);
                },
            }))
            : null;

        const wrapped = new Response(body, {
            status: response.status,
            statusText: response.statusText,
            headers: response.headers,
        });

        // Mediabunny resolves relative playlist paths against response.url once
        // response.redirected is set, so a proxied response must report the upstream URL.
        const finalUrl = viaServer ? response.headers.get('X-Hls-Final-Url') || requestedUrl : response.url || requestedUrl;

        Object.defineProperties(wrapped, {
            url: { value: finalUrl },
            redirected: { value: viaServer ? finalUrl !== requestedUrl : response.redirected },
            // Same-origin: every header is readable, which is what 'basic' tells Mediabunny.
            type: { value: viaServer ? 'basic' : response.type },
        });

        return wrapped;
    }

    async function transportFetch(input, init = {}) {
        const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
        const { origin } = new URL(url);
        const { mode, referer, extraHeaders } = getSettings();
        const encodedHeaders = mode === 'direct' ? null : encodeHeaders(buildRequestHeaders(referer, extraHeaders));

        // Browsers cannot send a Referer, cookies or a user agent of our choosing, so any
        // custom header means the server has to make the request.
        let route = mode === 'proxy' || encodedHeaders ? 'proxy' : mode === 'direct' ? 'direct' : routes.get(origin) ?? 'direct';
        let response = null;

        if (route === 'direct') {
            try {
                response = await fetch(url, { ...init, mode: 'cors', credentials: 'omit', referrerPolicy: 'no-referrer' });
            } catch (error) {
                if (error?.name === 'AbortError' || mode === 'direct') {
                    throw error;
                }

                route = 'proxy'; // blocked by CORS (or mixed content), or the network failed
            }

            // 401/403 to the browser is usually hotlink protection; the server may still get in.
            if (response && mode === 'auto' && (response.status === 401 || response.status === 403)) {
                response.body?.cancel().catch(() => {});
                response = null;
                route = 'proxy';
            }
        }

        if (route === 'proxy') {
            response = await viaProxy(url, init, encodedHeaders);
        }

        if (routes.get(origin) !== route) {
            routes.set(origin, route);
            onRoute(origin, route);
        }

        const wrapped = wrap(response, url, route === 'proxy');
        const claimedType = (route === 'proxy' ? response.headers.get('X-Hls-Upstream-Type') : response.headers.get('Content-Type')) ?? '';

        // Media playlists are where encryption is declared; check each one before Mediabunny parses it.
        if (wrapped.ok && (/\.m3u8?$/i.test(new URL(wrapped.url).pathname) || /mpegurl/i.test(claimedType))) {
            if (isDrmProtected(await wrapped.clone().text())) {
                throw new ToolError(DRM_MESSAGE);
            }
        }

        return wrapped;
    }

    return { fetch: transportFetch };
}

export function parseHttpUrl(raw) {
    const value = String(raw ?? '').trim();

    if (value.startsWith('blob:')) {
        throw new ToolError('blob: URLs only exist inside the tab that created them. Find the .m3u8 request in '
            + 'DevTools → Network instead (see the help below).');
    }

    let url;

    try {
        url = new URL(value);
    } catch {
        throw new ToolError('Enter a full URL, starting with https://');
    }

    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        throw new ToolError('Only http:// and https:// URLs are supported.');
    }

    url.hash = '';

    return url.href;
}

function statusMessage(status, host) {
    if (status === 401 || status === 403) {
        return `${host} refused the request (${status}). Set the Referer to the page the video plays on; if it `
            + 'still fails, copy that page\'s Cookie header into Extra headers.';
    }

    if (status === 404 || status === 410) {
        return `${host} answered ${status}. Signed playlist links expire, so grab a fresh one from the page.`;
    }

    if (status === 429) {
        return `${host} is rate-limiting requests (429). Wait a minute and try again.`;
    }

    return `${host} answered ${status}.`;
}

/** A readable sentence for anything inspect() or a download can throw. */
export function describeError(error) {
    if (error instanceof ToolError) {
        return error.message;
    }

    const message = String(error?.message ?? error);
    const failedFetch = /^Error fetching (\S+): (\d{3})/.exec(message);

    if (failedFetch) {
        let host = failedFetch[1];

        try {
            host = new URL(failedFetch[1]).host;
        } catch {
            // Keep the raw text.
        }

        return statusMessage(Number(failedFetch[2]), host);
    }

    if (/SAMPLE-AES|KEYFORMAT|resolveKeyId/i.test(message)) {
        return DRM_MESSAGE;
    }

    if (/Failed to fetch|NetworkError|Load failed/i.test(message)) {
        return 'The browser could not read that URL. Set Route to "Through the server" and try again.';
    }

    return message;
}

async function readText(response, maxBytes) {
    const reader = response.body?.getReader();

    if (!reader) {
        return '';
    }

    const chunks = [];
    let total = 0;

    while (total < maxBytes) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        chunks.push(value);
        total += value.byteLength;
    }

    reader.cancel().catch(() => {});

    const bytes = new Uint8Array(total);
    let offset = 0;

    for (const chunk of chunks) {
        bytes.set(chunk, offset);
        offset += chunk.byteLength;
    }

    return new TextDecoder().decode(bytes);
}

/** .m3u8 URLs in a page's source: attributes, inline JSON (escaped slashes) and JS strings. */
export function findPlaylists(html, pageUrl) {
    const text = html
        .replace(/\\u002f/gi, '/')
        .replace(/\\\//g, '/')
        .replace(/&amp;/g, '&')
        .replace(/https?%3A%2F%2F[^\s"'<>&]+/gi, (match) => {
            try {
                return decodeURIComponent(match);
            } catch {
                return match;
            }
        });

    const candidates = [
        ...text.matchAll(/https?:\/\/[^\s"'<>`\\()]+?\.m3u8(?:\?[^\s"'<>`\\()]*)?/gi),
        // Relative and protocol-relative paths, only when quoted (no colon: not an absolute URL).
        ...[...text.matchAll(/["'`]([^\s"'<>`\\:]+?\.m3u8(?:[?#][^\s"'<>`\\]*)?)["'`]/gi)].map((match) => [match[1]]),
    ];

    const found = new Set();

    for (const [candidate] of candidates) {
        try {
            const url = new URL(candidate, pageUrl);

            if (url.protocol === 'http:' || url.protocol === 'https:') {
                url.hash = '';
                found.add(url.href);
            }
        } catch {
            // Not a URL after all.
        }
    }

    return [...found].slice(0, 25);
}

function pageTitle(html) {
    // DOMParser documents are inert: no scripts run and nothing is fetched.
    const doc = new DOMParser().parseFromString(html, 'text/html');

    return (doc.querySelector('meta[property="og:title"]')?.getAttribute('content') || doc.title || '').trim();
}

async function openStream(playlistUrl, transport) {
    const input = new Input({
        source: new UrlSource(playlistUrl, { fetchFn: transport.fetch, getRetryDelay: retryDelay }),
        formats: HLS_FORMATS,
    });

    try {
        const videoTracks = await input.getVideoTracks({
            // #EXT-X-I-FRAME-STREAM-INF renditions are trick-play tracks, not something to save.
            filter: async (track) => !(await track.hasOnlyKeyPackets()),
            sortBy: async (track) => [desc(await track.getDisplayHeight()), desc((await track.getBitrate()) ?? 0)],
        });
        const audioTracks = await input.getAudioTracks();
        const primary = videoTracks[0] ?? audioTracks[0];

        if (!primary) {
            throw new ToolError('The playlist has no audio or video this tool can read.');
        }

        const videos = await Promise.all(videoTracks.map(async (track) => ({
            id: track.id,
            width: await track.getDisplayWidth(),
            height: await track.getDisplayHeight(),
            bitrate: await track.getBitrate(),
            codec: await track.getCodec(),
            audioIds: (await track.getPairableAudioTracks()).map((audio) => audio.id),
            primaryAudioId: (await track.getPrimaryPairableAudioTrack())?.id ?? null,
        })));

        const audios = await Promise.all(audioTracks.map(async (track) => ({
            id: track.id,
            name: await track.getName(),
            language: await track.getLanguageCode(),
            bitrate: await track.getBitrate(),
            codec: await track.getCodec(),
        })));

        // A live playlist's duration only resolves when the broadcast ends, so don't ask.
        const live = await primary.isLive();
        let duration = null;

        if (!live) {
            // Metadata durations are end timestamps, and HLS media does not always start at zero.
            const end = await input.getDurationFromMetadata([primary]);
            duration = end === null ? null : Math.max(0, end - Math.max(0, await input.getFirstTimestamp([primary])));
        }

        return { input, playlistUrl, live, duration, videos, audios };
    } catch (error) {
        input.dispose();
        throw error;
    }
}

/**
 * What did the admin paste? An HLS playlist: returns its tracks and the open Mediabunny
 * Input. A web page: returns the .m3u8 URLs found in its source.
 */
export async function inspect(rawUrl, transport) {
    const url = parseHttpUrl(rawUrl);
    const response = await transport.fetch(url, {});

    if (!response.ok) {
        response.body?.cancel().catch(() => {});
        throw new ToolError(statusMessage(response.status, new URL(url).host));
    }

    const finalUrl = response.url || url;
    const text = await readText(response, SNIFF_BYTES);

    if (text.trimStart().startsWith('#EXTM3U')) {
        if (isDrmProtected(text)) {
            throw new ToolError(DRM_MESSAGE);
        }

        return { kind: 'stream', ...(await openStream(finalUrl, transport)) };
    }

    if (/<(?:!doctype|html|head|body|script)\b/i.test(text)) {
        return { kind: 'page', pageUrl: finalUrl, title: pageTitle(text), playlists: findPlaylists(text, finalUrl) };
    }

    throw new ToolError('That URL is neither an HLS playlist (.m3u8) nor a web page.');
}

export function sanitizeFileName(name) {
    return String(name ?? '')
        .replace(/[ -<>:"/\\|?*]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/[. ]+$/, '')
        .slice(0, 120);
}

/** The page title when there is one, else the most specific part of the playlist path. */
export function suggestFileName(title, playlistUrl) {
    const fromTitle = sanitizeFileName(title);

    if (fromTitle) {
        return `${fromTitle}.mp4`;
    }

    const { hostname, pathname } = new URL(playlistUrl);
    const generic = /^(?:master|index|playlist|prog_index|chunklist.*|manifest|video|stream|hls|\d+p?|[0-9a-f-]{16,})$/i;
    const specific = pathname
        .split('/')
        .map((part) => {
            try {
                return decodeURIComponent(part).replace(/\.m3u8?$/i, '');
            } catch {
                return '';
            }
        })
        .filter((part) => part && !generic.test(part))
        .pop();

    return `${sanitizeFileName(specific) || hostname.replace(/^www\./, '')}.mp4`;
}

/** Lets a canceled or failed job throw the half-written file away instead of committing it. */
function discardableWritable(file) {
    let discard = false;

    return {
        stream: new WritableStream({
            write: (chunk) => file.write(chunk), // Mediabunny's { type: 'write', data, position } is exactly what the file stream takes
            close: () => (discard ? file.abort() : file.close()),
            abort: (reason) => file.abort(reason),
        }),
        discard: () => {
            discard = true;
        },
    };
}

/**
 * Copy one track's packets into the output, in decode order, and return how many were dropped.
 *
 * Some packagers cut HLS segments that overlap by a few frames. Players cope (MSE removes
 * the overlap), but the MP4 muxer rejects a GOP that starts before the previous one ended.
 * So each GOP is held until the next key packet arrives; if that key packet starts inside
 * the held GOP, the held GOP loses its tail from the first overlapping packet on. That is
 * what MSE removes too, and it never drops a frame a kept one depends on: references
 * always come before their dependents in decode order.
 */
async function copyTrack({ track, source, offset, isCanceled, onPacket }) {
    const decoderConfig = await track.getDecoderConfig();
    let meta = decoderConfig ? { decoderConfig } : undefined;
    let held = [];
    let heldMax = -Infinity;
    let writtenMax = -Infinity;
    let gopFloor = -Infinity;
    let skipping = false;
    let dropped = 0;

    const writeHeld = async () => {
        for (const packet of held) {
            await source.add(packet.clone({ timestamp: packet.timestamp + offset }), meta);
            meta = undefined;
            writtenMax = Math.max(writtenMax, packet.timestamp);
        }

        held = [];
        heldMax = -Infinity;
    };

    for await (const packet of new EncodedPacketSink(track).packets()) {
        if (isCanceled()) {
            return dropped;
        }

        onPacket(packet.timestamp);

        if (packet.type === 'key') {
            if (packet.timestamp < heldMax) {
                const cut = held.findIndex((earlier) => earlier.timestamp >= packet.timestamp);
                dropped += held.length - cut;
                held.length = cut;
            }

            await writeHeld();

            // An overlap reaching back past what is already written: skip this whole GOP.
            skipping = packet.timestamp < writtenMax;

            if (skipping) {
                dropped++;
                continue;
            }

            gopFloor = writtenMax;
            held = [packet];
            heldMax = packet.timestamp;
        } else if (skipping || held.length === 0 || packet.timestamp < gopFloor) {
            dropped++; // before the first key packet, inside a skipped GOP, or behind what is written
        } else {
            held.push(packet);
            heldMax = Math.max(heldMax, packet.timestamp);
        }
    }

    if (!isCanceled()) {
        await writeHeld();
        source.close();
    }

    return dropped;
}

async function addOutputTrack(output, track) {
    const codec = await track.getCodec();

    if (!codec) {
        throw new ToolError(`This stream's ${track.type} codec is not one this tool can copy.`);
    }

    if (track.isVideoTrack()) {
        const source = new EncodedVideoPacketSource(codec);
        output.addVideoTrack(source, { rotation: await track.getRotation() });

        return source;
    }

    const source = new EncodedAudioPacketSource(codec);
    const name = await track.getName();
    const language = await track.getLanguageCode();

    output.addAudioTrack(source, {
        ...(name ? { name } : {}),
        // MP4 wants ISO 639-2 (three letters); HLS playlists usually say "en".
        ...(/^[a-z]{3}$/.test(language) && language !== 'und' ? { languageCode: language } : {}),
    });

    return source;
}

/**
 * Copy the chosen tracks into an MP4 without re-encoding. With a FileSystemWritableFileStream
 * (Chromium's save picker) the file streams to disk; otherwise it is built in memory and
 * `done` resolves with a Blob. `dropped` counts packets trimmed from overlapping segments.
 *
 * @returns {{ done: Promise<{ blob: Blob|null, dropped: number }>, cancel: () => Promise<void> }}
 */
export function startDownload({ input, videoId, audioId, fileWritable = null, fastStart = true, onProgress = () => {} }) {
    const sink = fileWritable ? discardableWritable(fileWritable) : null;
    const target = sink ? new StreamTarget(sink.stream, { chunked: true }) : new BufferTarget();
    const output = new Output({
        // A buffer target holds the whole file anyway, so it may as well put the index first.
        format: new Mp4OutputFormat({ fastStart: (fastStart || !sink) ? 'in-memory' : false }),
        target,
    });

    let canceled = false;
    const isCanceled = () => canceled;

    const run = async () => {
        const tracks = (await input.getTracks()).filter((track) => track.id === videoId || track.id === audioId);

        if (tracks.length === 0) {
            throw new ToolError('Choose a video quality or an audio track first.');
        }

        // As Mediabunny's Conversion does: the file starts at zero and the tracks keep their relative sync.
        const start = Math.max(0, await input.getFirstTimestamp(tracks));
        // Metadata durations are end timestamps, so subtract where the media starts.
        const duration = Math.max(0, ((await input.getDurationFromMetadata(tracks)) ?? 0) - start);
        const sources = [];

        for (const track of tracks) {
            sources.push(await addOutputTrack(output, track));
        }

        await output.start();

        // Progress follows the track that is furthest behind.
        const positions = new Map(tracks.map((track) => [track.id, 0]));
        const report = () => {
            const behind = Math.min(...positions.values());

            if (duration > 0 && Number.isFinite(behind)) {
                onProgress(Math.min(0.999, behind / duration));
            }
        };

        const dropped = await Promise.all(tracks.map(async (track, index) => {
            const count = await copyTrack({
                track,
                source: sources[index],
                offset: -start,
                isCanceled,
                onPacket: (timestamp) => {
                    positions.set(track.id, Math.max(0, timestamp - start));
                    report();
                },
            });
            positions.set(track.id, Infinity);

            return count;
        }));

        if (canceled) {
            throw new DownloadCanceledError();
        }

        await output.finalize();
        onProgress(1);

        return {
            blob: sink ? null : new Blob([target.buffer], { type: 'video/mp4' }),
            dropped: dropped.reduce((sum, count) => sum + count, 0),
        };
    };

    const settleOutput = async () => {
        sink?.discard();

        if (output.state === 'pending' || output.state === 'started') {
            await output.cancel().catch(() => {});
        }
    };

    const done = run().catch(async (error) => {
        await settleOutput();
        throw canceled ? new DownloadCanceledError() : error;
    });

    return {
        done,
        async cancel() {
            canceled = true;
            // Unblocks a track waiting on the muxer for the other one; a track waiting on the
            // network stops at its next packet.
            await settleOutput();
        },
    };
}
