import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

// Admin-only: save an HLS (.m3u8) stream as an MP4. Everything runs in this tab; the
// engine (lib/hls-download.js, built on Mediabunny) is imported on first use, and the
// server only gets involved when a CDN blocks the browser (HlsDownloaderController::proxy).
const loadEngine = () => import('@/lib/hls-download');

// "Fast start" holds the whole file in memory until the end, so above this estimate it
// defaults to off when the MP4 streams to disk.
const FAST_START_LIMIT = 1.5 * 1024 ** 3;

const ROUTE_OPTIONS = [
    {
        value: 'auto',
        label: 'Auto',
        hint: 'Read each host from this browser; switch it to the server when it blocks cross-origin reads or answers 401/403.',
    },
    {
        value: 'proxy',
        label: 'Through the server',
        hint: 'Every request goes through this site. Happens automatically once a Referer or extra headers are set.',
    },
    {
        value: 'direct',
        label: 'Browser only',
        hint: 'Never involve the server. Fails on CDNs that block cross-origin reads, and ignores the headers above.',
    },
];

export default function HlsDownloader() {
    const [url, setUrl] = useState('');
    const [referer, setReferer] = useState('');
    const [extraHeaders, setExtraHeaders] = useState('');
    const [mode, setMode] = useState('auto');
    const [showOptions, setShowOptions] = useState(false);

    const [status, setStatus] = useState('idle'); // idle | inspecting | page | stream | downloading | done
    const [error, setError] = useState(null);
    const [page, setPage] = useState(null);
    const [stream, setStream] = useState(null);
    const [videoId, setVideoId] = useState(null);
    const [audioId, setAudioId] = useState(null);
    const [fileName, setFileName] = useState('');
    const [fastStart, setFastStart] = useState(true);
    const [progress, setProgress] = useState(null);
    const [hosts, setHosts] = useState({});
    const [result, setResult] = useState(null);

    const settings = useRef({ mode, referer, extraHeaders });
    const input = useRef(null);
    const job = useRef(null);
    const counters = useRef({ bytes: 0, ratio: 0 });
    const title = useRef('');

    const canSaveToDisk = typeof window.showSaveFilePicker === 'function';
    const busy = status === 'inspecting' || status === 'downloading';

    // The transport reads these per request, so edits apply to the next request.
    useEffect(() => {
        settings.current = { mode, referer, extraHeaders };
    }, [mode, referer, extraHeaders]);

    // While downloading: refresh the readout twice a second and guard against closing the tab.
    useEffect(() => {
        if (status !== 'downloading') {
            return undefined;
        }

        const timer = setInterval(() => {
            setProgress((current) => current && { ...current, ...counters.current, now: Date.now() });
        }, 500);
        const warn = (event) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);

        return () => {
            clearInterval(timer);
            window.removeEventListener('beforeunload', warn);
        };
    }, [status]);

    // Leaving the page cancels a running download and releases the stream.
    useEffect(() => () => {
        job.current?.cancel();
        input.current?.dispose();
    }, []);

    // An object URL pins the finished MP4 in memory until it is revoked.
    useEffect(() => () => {
        if (result?.href) {
            URL.revokeObjectURL(result.href);
        }
    }, [result]);

    function transportFor(engine) {
        return engine.createTransport({
            proxyUrl: route('admin.tools.hls-downloader.proxy'),
            getSettings: () => settings.current,
            onRoute: (origin, via) => setHosts((current) => ({ ...current, [new URL(origin).host]: via })),
            onBytes: (count) => {
                counters.current.bytes += count;
            },
        });
    }

    function defaultFastStart(details, video, audio) {
        const estimate = estimateBytes(video?.bitrate ?? audio?.bitrate, details.duration);

        return !canSaveToDisk || estimate === null || estimate <= FAST_START_LIMIT;
    }

    async function inspect(target, { fromPage = false } = {}) {
        input.current?.dispose();
        input.current = null;
        setError(null);
        setResult(null);
        setStream(null);
        setHosts({});

        if (!fromPage) {
            title.current = '';
            setPage(null);
        }

        setStatus('inspecting');
        let engine = null;

        try {
            engine = await loadEngine();
            const found = await engine.inspect(target, transportFor(engine));

            if (found.kind === 'page') {
                title.current = found.title;
                setPage(found);
                setStatus('page');

                return;
            }

            const { input: opened, ...details } = found;
            const video = details.videos[0] ?? null;
            const audio = details.audios.find((track) => track.id === (video ? video.primaryAudioId ?? video.audioIds[0] : details.audios[0]?.id)) ?? null;

            input.current = opened;
            setStream(details);
            setVideoId(video?.id ?? null);
            setAudioId(audio?.id ?? null);
            setFileName(engine.suggestFileName(title.current, details.playlistUrl));
            setFastStart(defaultFastStart(details, video, audio));
            setStatus('stream');
        } catch (e) {
            setError(engine ? engine.describeError(e) : `The downloader failed to load: ${e.message}`);
            setStatus(fromPage ? 'page' : 'idle');
        }
    }

    function pickPlaylist(playlistUrl) {
        setUrl(playlistUrl);

        // A playlist found on a page usually expects that page as its Referer.
        if (!referer.trim() && page) {
            setReferer(page.pageUrl);
            setShowOptions(true);
            settings.current = { ...settings.current, referer: page.pageUrl };
        }

        inspect(playlistUrl, { fromPage: true });
    }

    function selectVideo(id) {
        const video = stream.videos.find((track) => track.id === id);
        const audio = stream.audios.find((track) => track.id === (video.primaryAudioId ?? video.audioIds[0])) ?? null;

        setVideoId(id);
        setAudioId(audio?.id ?? null);
        setFastStart(defaultFastStart(stream, video, audio));
    }

    async function download() {
        const name = ensureMp4(fileName);
        let fileHandle = null;

        if (canSaveToDisk) {
            try {
                // The first await in the click handler: the picker needs the click's user activation.
                fileHandle = await window.showSaveFilePicker({
                    suggestedName: name,
                    types: [{ description: 'MP4 video', accept: { 'video/mp4': ['.mp4'] } }],
                });
            } catch (e) {
                if (e?.name === 'AbortError') {
                    return; // picker dismissed
                }
                // Anything else (e.g. a blocked picker): build the file in memory instead.
            }
        }

        const engine = await loadEngine();
        const startedAt = Date.now();
        counters.current = { bytes: 0, ratio: 0 };
        setProgress({ bytes: 0, ratio: 0, startedAt, now: startedAt });
        setError(null);
        setResult(null);
        setStatus('downloading');

        try {
            job.current = engine.startDownload({
                input: input.current,
                videoId,
                audioId,
                fileWritable: fileHandle ? await fileHandle.createWritable() : null,
                fastStart,
                onProgress: (ratio) => {
                    counters.current.ratio = ratio;
                },
            });

            const { blob, dropped } = await job.current.done;
            const href = blob ? URL.createObjectURL(blob) : null;

            if (href) {
                saveBlob(href, name);
            }

            setResult({
                name: fileHandle?.name ?? name,
                href,
                bytes: blob ? blob.size : (await fileHandle.getFile()).size,
                seconds: (Date.now() - startedAt) / 1000,
                dropped,
            });
            setStatus('done');
        } catch (e) {
            const canceled = e instanceof engine.DownloadCanceledError;

            if (canceled && fileHandle) {
                try {
                    await fileHandle.remove?.();
                } catch {
                    // Older Chromium cannot delete it; the aborted write left it empty.
                }
            }

            setError(canceled ? 'Download canceled.' : engine.describeError(e));
            setStatus('stream');
        } finally {
            job.current = null;
        }
    }

    const selectedVideo = stream?.videos.find((track) => track.id === videoId) ?? null;
    const selectedAudio = stream?.audios.find((track) => track.id === audioId) ?? null;
    const audioChoices = !stream ? [] : selectedVideo
        ? stream.audios.filter((track) => selectedVideo.audioIds.includes(track.id))
        : stream.audios;
    const estimate = stream ? estimateBytes(selectedVideo?.bitrate ?? selectedAudio?.bitrate, stream.duration) : null;
    const headersSet = referer.trim() !== '' || extraHeaders.trim() !== '';

    return (
        <AuthenticatedLayout header={
            <div>
                <h2 className="text-xl font-semibold text-gray-800">HLS Downloader</h2>
                <p className="text-sm text-gray-500 mt-0.5">
                    Save an .m3u8 stream as an MP4. Paste the playlist URL, or the page the video plays on.
                </p>
            </div>
        }>
            <Head title="HLS Downloader">
                <meta head-key="robots" name="robots" content="noindex, nofollow" />
            </Head>

            <div className="py-8 px-4 max-w-3xl mx-auto space-y-6">

                <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (url.trim()) inspect(url.trim());
                        }}
                        className="p-6 space-y-4"
                    >
                        <div className="space-y-1.5">
                            <label htmlFor="hls-url" className="block text-sm font-medium text-gray-700">
                                Playlist or page URL
                            </label>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <input
                                    id="hls-url"
                                    type="url"
                                    value={url}
                                    onChange={(e) => setUrl(e.target.value)}
                                    placeholder="https://example.com/video/master.m3u8"
                                    required
                                    disabled={busy}
                                    className="min-w-0 flex-1 text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:bg-gray-50"
                                />
                                <button
                                    type="submit"
                                    disabled={busy || !url.trim()}
                                    className="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                                               px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
                                    {status === 'inspecting' ? <><Spinner />Inspecting...</> : 'Inspect'}
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            onClick={() => setShowOptions((shown) => !shown)}
                            aria-expanded={showOptions}
                            className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                            {showOptions ? 'Hide request options' : `Request options${headersSet ? ' (headers set)' : ''}`}
                        </button>

                        {showOptions && (
                            <div className="space-y-4 border-t border-gray-100 pt-4">
                                <div className="space-y-1.5">
                                    <label htmlFor="hls-referer" className="block text-sm font-medium text-gray-700">
                                        Referer
                                        <span className="ml-1.5 text-xs font-normal text-gray-500">
                                            (the page the video plays on; a matching Origin is sent too)
                                        </span>
                                    </label>
                                    <input
                                        id="hls-referer"
                                        type="url"
                                        value={referer}
                                        onChange={(e) => setReferer(e.target.value)}
                                        placeholder="https://example.com/watch/123"
                                        disabled={status === 'downloading'}
                                        className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:bg-gray-50"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="hls-headers" className="block text-sm font-medium text-gray-700">
                                        Extra headers
                                        <span className="ml-1.5 text-xs font-normal text-gray-500">
                                            (one "Name: value" per line; a block copied from DevTools works)
                                        </span>
                                    </label>
                                    <textarea
                                        id="hls-headers"
                                        rows={4}
                                        value={extraHeaders}
                                        onChange={(e) => setExtraHeaders(e.target.value)}
                                        placeholder={'Cookie: session=...\nUser-Agent: ...'}
                                        disabled={status === 'downloading'}
                                        className="w-full font-mono text-xs text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                    />
                                </div>

                                <fieldset className="space-y-2">
                                    <legend className="text-sm font-medium text-gray-700">Route</legend>
                                    {ROUTE_OPTIONS.map((option) => (
                                        <label key={option.value} className="flex items-start gap-2.5 text-sm">
                                            <input
                                                type="radio"
                                                name="hls-route"
                                                value={option.value}
                                                checked={mode === option.value}
                                                onChange={() => setMode(option.value)}
                                                disabled={status === 'downloading'}
                                                className="mt-0.5 text-indigo-600 focus:ring-indigo-400"
                                            />
                                            <span>
                                                <span className="font-medium text-gray-800">{option.label}</span>
                                                <span className="block text-xs text-gray-500">{option.hint}</span>
                                            </span>
                                        </label>
                                    ))}
                                </fieldset>
                            </div>
                        )}
                    </form>
                </section>

                {error && (
                    <p role="alert" className="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                        {error}
                    </p>
                )}

                {page && (
                    <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100">
                            <h3 className="text-sm font-semibold text-gray-800">
                                {page.playlists.length === 0
                                    ? 'No .m3u8 links in the page source'
                                    : `Found ${page.playlists.length === 1 ? '1 playlist' : `${page.playlists.length} playlists`} on the page`}
                            </h3>
                            {page.title && <p className="text-xs text-gray-500 mt-0.5 truncate">{page.title}</p>}
                        </div>
                        {page.playlists.length === 0 ? (
                            <div className="px-6 py-4 text-sm text-gray-600 space-y-2">
                                <p>The player probably requests its playlist from JavaScript. Find it in DevTools instead:</p>
                                <FindPlaylistSteps />
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {page.playlists.map((playlist) => (
                                    <li key={playlist} className="px-6 py-3 flex items-center gap-4">
                                        <code className="min-w-0 flex-1 truncate text-xs text-gray-700" title={playlist}>
                                            {playlist}
                                        </code>
                                        <button
                                            type="button"
                                            onClick={() => pickPlaylist(playlist)}
                                            disabled={busy}
                                            className="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-800 disabled:opacity-40">
                                            Use
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {stream && (
                    <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <h3 className="text-sm font-semibold text-gray-800">Stream</h3>
                            <span className="text-xs text-gray-500">
                                {[
                                    formatDuration(stream.duration),
                                    plural(stream.videos.length, 'quality', 'qualities'),
                                    plural(stream.audios.length, 'audio track', 'audio tracks'),
                                ].join(' · ')}
                            </span>
                            {stream.live && (
                                <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Live</span>
                            )}
                        </div>

                        {stream.live ? (
                            <p className="px-6 py-4 text-sm text-gray-600">
                                This is a live broadcast. Only finished videos can be saved; once a replay is published, inspect its playlist instead.
                            </p>
                        ) : (
                            <div className="p-6 space-y-5">
                                {stream.videos.length > 0 && (
                                    <fieldset className="space-y-2">
                                        <legend className="text-sm font-medium text-gray-700">Quality</legend>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {stream.videos.map((video) => (
                                                <label
                                                    key={video.id}
                                                    className={`flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm cursor-pointer ${
                                                        video.id === videoId ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'
                                                    }`}>
                                                    <input
                                                        type="radio"
                                                        name="hls-video"
                                                        checked={video.id === videoId}
                                                        onChange={() => selectVideo(video.id)}
                                                        disabled={status === 'downloading'}
                                                        className="text-indigo-600 focus:ring-indigo-400"
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block font-medium text-gray-800">
                                                            {video.height ? `${video.height}p` : 'Video'}
                                                            {video.width > 0 && video.height > 0 && (
                                                                <span className="font-normal text-gray-500"> · {video.width}×{video.height}</span>
                                                            )}
                                                        </span>
                                                        <span className="block truncate text-xs text-gray-500">
                                                            {[formatBitrate(video.bitrate), video.codec].filter(Boolean).join(' · ')}
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
                                )}

                                {audioChoices.length > 0 && (
                                    <div className="space-y-1.5">
                                        <label htmlFor="hls-audio" className="block text-sm font-medium text-gray-700">Audio</label>
                                        <select
                                            id="hls-audio"
                                            value={audioId ?? ''}
                                            onChange={(e) => setAudioId(e.target.value === '' ? null : Number(e.target.value))}
                                            disabled={status === 'downloading'}
                                            className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                            {audioChoices.map((audio) => (
                                                <option key={audio.id} value={audio.id}>{audioLabel(audio)}</option>
                                            ))}
                                            {selectedVideo && <option value="">No audio</option>}
                                        </select>
                                    </div>
                                )}

                                <div className="space-y-1.5">
                                    <label htmlFor="hls-filename" className="block text-sm font-medium text-gray-700">File name</label>
                                    <input
                                        id="hls-filename"
                                        type="text"
                                        value={fileName}
                                        onChange={(e) => setFileName(e.target.value)}
                                        disabled={status === 'downloading'}
                                        className="w-full text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-400 disabled:bg-gray-50"
                                    />
                                    <p className="text-xs text-gray-500">
                                        {estimate ? `About ${formatBytes(estimate)}. ` : ''}
                                        {canSaveToDisk
                                            ? 'You choose where to save it, and the MP4 is written straight to disk.'
                                            : 'This browser builds the MP4 in memory, then downloads it. Chrome or Edge can write straight to disk instead.'}
                                    </p>
                                </div>

                                {canSaveToDisk && (
                                    <label className="flex items-start gap-2.5 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={fastStart}
                                            onChange={(e) => setFastStart(e.target.checked)}
                                            disabled={status === 'downloading'}
                                            className="mt-0.5 rounded text-indigo-600 focus:ring-indigo-400"
                                        />
                                        <span>
                                            <span className="font-medium text-gray-800">Fast start</span>
                                            <span className="block text-xs text-gray-500">
                                                Puts the index first so web players start without reading the whole file. Keeps the video in memory until it finishes; leave it off for very long videos.
                                            </span>
                                        </span>
                                    </label>
                                )}

                                {status === 'downloading' && progress ? (
                                    <DownloadProgress progress={progress} hosts={hosts} onCancel={() => job.current?.cancel()} />
                                ) : (
                                    <button
                                        type="button"
                                        onClick={download}
                                        disabled={busy || (videoId === null && audioId === null) || !fileName.trim()}
                                        className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                                                   px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
                                        <DownloadIcon className="w-4 h-4" />
                                        Download MP4
                                    </button>
                                )}
                            </div>
                        )}
                    </section>
                )}

                {status === 'done' && result && (
                    <section className="bg-emerald-50 border border-emerald-200 rounded-xl px-6 py-4 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <span className="text-sm font-semibold text-emerald-800">Saved {result.name}</span>
                        <span className="text-xs text-emerald-700">
                            {formatBytes(result.bytes)} in {formatDuration(result.seconds)}
                            {result.dropped > 0 && ` · ${plural(result.dropped, 'duplicate frame', 'duplicate frames')} trimmed where segments overlapped`}
                        </span>
                        {result.href && (
                            <a href={result.href} download={result.name} className="text-xs font-semibold text-emerald-800 underline">
                                Save again
                            </a>
                        )}
                    </section>
                )}

                <details className="bg-white border border-gray-200 rounded-xl px-6 py-4 text-sm text-gray-600">
                    <summary className="cursor-pointer font-semibold text-gray-800">Finding the .m3u8, and what to do when a site refuses</summary>
                    <div className="mt-3 space-y-3">
                        <FindPlaylistSteps />
                        <p>
                            DRM-protected streams (FairPlay, Widevine, PlayReady) are refused, and live broadcasts can't be saved.
                            Standard AES-128 encrypted streams download normally.
                        </p>
                    </div>
                </details>
            </div>
        </AuthenticatedLayout>
    );
}

function DownloadProgress({ progress, hosts, onCancel }) {
    const elapsed = (progress.now - progress.startedAt) / 1000;
    const percent = Math.min(100, Math.round(progress.ratio * 100));
    const remaining = progress.ratio > 0.01 ? (elapsed * (1 - progress.ratio)) / progress.ratio : null;

    return (
        <div className="space-y-3" aria-live="polite">
            <div className="h-2 rounded-full bg-gray-100 overflow-hidden">
                <div
                    className="h-full bg-indigo-600 transition-all duration-500 motion-reduce:transition-none"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
                <span className="font-semibold text-gray-800">{percent}%</span>
                <span>{formatBytes(progress.bytes)} downloaded</span>
                {elapsed > 0 && <span>{formatBytes(progress.bytes / elapsed)}/s</span>}
                <span>{formatDuration(elapsed)} elapsed</span>
                {remaining !== null && <span>about {formatDuration(remaining)} left</span>}
                <button type="button" onClick={onCancel} className="ml-auto font-semibold text-red-600 hover:text-red-800">
                    Cancel
                </button>
            </div>
            {Object.keys(hosts).length > 0 && (
                <ul className="text-xs text-gray-500 space-y-0.5">
                    {Object.entries(hosts).map(([host, via]) => (
                        <li key={host}>
                            <code>{host}</code> {via === 'proxy' ? 'through the server' : 'direct from this browser'}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function FindPlaylistSteps() {
    return (
        <ol className="list-decimal pl-5 space-y-1">
            <li>Open the page with the video, press F12, and switch to the <strong>Network</strong> tab.</li>
            <li>Type <code>m3u8</code> into the filter box, then start the video.</li>
            <li>Right-click the first match (often <code>master.m3u8</code>), choose Copy → <strong>Copy URL</strong>, and paste it above.</li>
            <li>
                Refused with 401 or 403? Put the video page's URL in <strong>Referer</strong>. Still refused? Copy that
                request's Cookie header into <strong>Extra headers</strong>.
            </li>
        </ol>
    );
}

function estimateBytes(bitrate, seconds) {
    return bitrate && seconds ? (bitrate / 8) * seconds : null;
}

function ensureMp4(name) {
    const trimmed = name.trim().replace(/[ -<>:"/\\|?*]+/g, ' ') || 'video';

    return /\.mp4$/i.test(trimmed) ? trimmed : `${trimmed}.mp4`;
}

function saveBlob(href, name) {
    const link = document.createElement('a');
    link.href = href;
    link.download = name;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

function audioLabel(audio) {
    const language = audio.language && audio.language !== 'und' ? audio.language : null;

    return [audio.name || language || 'Default audio', audio.name ? language : null, audio.codec, formatBitrate(audio.bitrate)]
        .filter(Boolean)
        .join(' · ');
}

function plural(count, one, many) {
    return `${count} ${count === 1 ? one : many}`;
}

function formatBytes(bytes) {
    if (!bytes) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** power).toFixed(power === 0 ? 0 : 1)} ${units[power]}`;
}

function formatBitrate(bitsPerSecond) {
    if (!bitsPerSecond) {
        return null;
    }

    return bitsPerSecond >= 1e6 ? `${(bitsPerSecond / 1e6).toFixed(1)} Mbps` : `${Math.round(bitsPerSecond / 1e3)} kbps`;
}

function formatDuration(seconds) {
    if (seconds === null || seconds === undefined || !Number.isFinite(seconds)) {
        return 'unknown length';
    }

    const total = Math.round(seconds);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = String(total % 60).padStart(2, '0');

    return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${secs}` : `${minutes}:${secs}`;
}

function Spinner() {
    return (
        <svg className="animate-spin motion-reduce:animate-none w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    );
}

function DownloadIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
    );
}
