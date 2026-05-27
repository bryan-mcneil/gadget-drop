import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useRef } from 'react';

const SUBREDDITS = [
    'techsupport', 'windows', 'apple', 'Android', 'privacy',
    'homelab', 'hardware', 'pcmasterrace', 'buildapc',
    'linuxquestions', 'gadgets', 'technology', 'smartphones', 'iphone',
];

const TIME_OPTIONS = [
    { value: 'week',  label: 'Past Week' },
    { value: 'month', label: 'Past Month' },
    { value: 'year',  label: 'Past Year' },
    { value: 'all',   label: 'All Time' },
];

function timeAgo(utc) {
    const seconds = Math.floor(Date.now() / 1000 - utc);
    if (seconds < 3600)  return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function extractPermalink(input) {
    const trimmed = input.trim();
    try {
        const u = new URL(trimmed);
        if (u.hostname.includes('reddit.com')) return u.pathname;
    } catch {}
    if (trimmed.startsWith('/r/')) return trimmed;
    return null;
}

export default function TechTipsIndex() {
    const [keyword,   setKeyword]   = useState('');
    const [subreddit, setSubreddit] = useState('techsupport');
    const [time,      setTime]      = useState('month');
    const [searching, setSearching] = useState(false);
    const [results,   setResults]   = useState([]);
    const [error,     setError]     = useState(null);

    const [directUrl, setDirectUrl] = useState('');

    // Which thread is currently in the "prepare" (prompt-copy) step
    const [activePermalink, setActivePermalink] = useState(null);
    const [promptData,      setPromptData]      = useState(null); // { prompt, source_url, title }
    const [preparing,       setPreparing]       = useState(false);

    async function handleDirectUrl(e) {
        e.preventDefault();
        const permalink = extractPermalink(directUrl);
        if (!permalink) {
            setError('Paste a full Reddit thread URL (e.g. https://www.reddit.com/r/…/comments/…/)');
            return;
        }
        setError(null);
        handlePrepare(permalink);
    }

    async function handleSearch(e) {
        e.preventDefault();
        if (!keyword.trim()) return;
        setSearching(true);
        setError(null);
        setResults([]);
        setActivePermalink(null);
        setPromptData(null);

        try {
            const params = new URLSearchParams({ query: keyword, subreddit, time });
            const res    = await fetch(`${route('admin.tech-tips.search')}?${params}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Search failed');
            setResults(data.results ?? []);
            if ((data.results ?? []).length === 0) setError('No threads found — try a different keyword or time range.');
        } catch (err) {
            setError(err.message);
        } finally {
            setSearching(false);
        }
    }

    async function handlePrepare(permalink) {
        if (preparing) return;
        setActivePermalink(permalink);
        setPromptData(null);
        setPreparing(true);
        setError(null);

        try {
            const params = new URLSearchParams({ permalink });
            const res    = await fetch(`${route('admin.tech-tips.prepare')}?${params}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Failed to fetch thread');
            setPromptData(data);
        } catch (err) {
            setError(err.message);
            setActivePermalink(null);
        } finally {
            setPreparing(false);
        }
    }

    function handleClose() {
        setActivePermalink(null);
        setPromptData(null);
    }

    return (
        <AuthenticatedLayout header={
            <div>
                <h2 className="text-xl font-semibold text-gray-800">Tech Tips Generator</h2>
                <p className="text-sm text-gray-400 mt-0.5">
                    Search Reddit for top questions, copy the prompt to claude.ai, then paste the response to create a draft.
                </p>
            </div>
        }>
            <Head title="Tech Tips Generator" />

            <div className="py-8 px-4 max-w-5xl mx-auto space-y-6">

                {/* Workflow explanation */}
                <div className="bg-indigo-50 border border-indigo-100 rounded-xl p-4 flex gap-4 items-start">
                    <div className="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                        <svg className="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
                        </svg>
                    </div>
                    <div className="text-sm text-indigo-800 space-y-0.5">
                        <p className="font-semibold">3-step workflow</p>
                        <p className="text-indigo-600">
                            <span className="font-medium">1.</span> Search Reddit and pick a thread ·{' '}
                            <span className="font-medium">2.</span> Copy the generated prompt into{' '}
                            <a href="https://claude.ai" target="_blank" rel="noopener noreferrer"
                                className="underline hover:text-indigo-800">claude.ai</a> ·{' '}
                            <span className="font-medium">3.</span> Paste Claude's JSON reply here to create the draft post.
                        </p>
                    </div>
                </div>

                {/* Direct URL input */}
                <form onSubmit={handleDirectUrl}
                    className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <h3 className="font-semibold text-gray-700 text-sm">Have a Reddit URL? Paste it directly</h3>
                    <div className="flex gap-2">
                        <input
                            type="url"
                            value={directUrl}
                            onChange={e => setDirectUrl(e.target.value)}
                            placeholder="https://www.reddit.com/r/techsupport/comments/…"
                            className="flex-1 border-gray-300 rounded-lg shadow-sm text-sm"
                        />
                        <button
                            type="submit"
                            disabled={preparing || !directUrl.trim()}
                            className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white
                                       px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-40 whitespace-nowrap"
                        >
                            {preparing && !results.length ? (
                                <>
                                    <Spinner />
                                    Loading…
                                </>
                            ) : (
                                <>
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Get Prompt
                                </>
                            )}
                        </button>
                    </div>
                </form>

                <div className="flex items-center gap-3 text-xs text-gray-400">
                    <div className="flex-1 h-px bg-gray-200" />
                    or search Reddit below
                    <div className="flex-1 h-px bg-gray-200" />
                </div>

                {/* Search form */}
                <form onSubmit={handleSearch}
                    className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                    <h3 className="font-semibold text-gray-700 text-sm">Step 1 — Find a Reddit Thread</h3>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input
                            type="text"
                            value={keyword}
                            onChange={e => setKeyword(e.target.value)}
                            placeholder="e.g. wifi keeps dropping"
                            required
                            className="sm:col-span-1 border-gray-300 rounded-lg shadow-sm text-sm"
                        />
                        <div className="flex gap-3 sm:col-span-2">
                            <div className="flex items-center gap-2 flex-1">
                                <span className="text-sm text-gray-500 whitespace-nowrap">r/</span>
                                <select
                                    value={subreddit}
                                    onChange={e => setSubreddit(e.target.value)}
                                    className="flex-1 border-gray-300 rounded-lg shadow-sm text-sm"
                                >
                                    {SUBREDDITS.map(s => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>
                            <select
                                value={time}
                                onChange={e => setTime(e.target.value)}
                                className="border-gray-300 rounded-lg shadow-sm text-sm"
                            >
                                {TIME_OPTIONS.map(o => (
                                    <option key={o.value} value={o.value}>{o.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={searching}
                            className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
                        >
                            {searching ? (
                                <>
                                    <Spinner />
                                    Searching…
                                </>
                            ) : (
                                <>
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                                    </svg>
                                    Search Reddit
                                </>
                            )}
                        </button>
                        <p className="text-xs text-gray-400">Results sorted by top score for the selected time period.</p>
                    </div>
                </form>

                {/* Error */}
                {error && (
                    <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                        {error}
                    </div>
                )}

                {/* Prompt panel (Step 2 + 3) */}
                {activePermalink && (
                    <PromptPanel
                        preparing={preparing}
                        promptData={promptData}
                        onClose={handleClose}
                    />
                )}

                {/* Results */}
                {results.length > 0 && (
                    <div className="space-y-3">
                        <p className="text-xs text-gray-400 font-medium uppercase tracking-widest">
                            {results.length} thread{results.length !== 1 ? 's' : ''} found in r/{subreddit}
                        </p>
                        {results.map(thread => (
                            <ThreadCard
                                key={thread.id}
                                thread={thread}
                                isActive={activePermalink === thread.permalink}
                                isPreparing={preparing && activePermalink === thread.permalink}
                                disabled={preparing}
                                onGetPrompt={() => handlePrepare(thread.permalink)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

/* ── Prompt Panel (Steps 2 & 3) ───────────────────────────────── */
function PromptPanel({ preparing, promptData, onClose }) {
    const [copied,       setCopied]       = useState(false);
    const [jsonResponse, setJsonResponse] = useState('');
    const [creating,     setCreating]     = useState(false);
    const [createError,  setCreateError]  = useState(null);
    const textareaRef = useRef(null);

    async function copyPrompt() {
        if (!promptData?.prompt) return;
        try {
            await navigator.clipboard.writeText(promptData.prompt);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            if (textareaRef.current) {
                textareaRef.current.select();
                document.execCommand('copy');
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            }
        }
    }

    async function handleCreate() {
        if (!jsonResponse.trim() || !promptData?.source_url) return;
        setCreating(true);
        setCreateError(null);

        try {
            const res = await fetch(route('admin.tech-tips.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    source_url:    promptData.source_url,
                    json_response: jsonResponse,
                }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Failed to create draft');
            window.location.href = data.redirect;
        } catch (err) {
            setCreateError(err.message);
            setCreating(false);
        }
    }

    return (
        <div className="bg-white border border-indigo-200 rounded-xl shadow-sm overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between px-5 py-4 border-b border-indigo-100 bg-indigo-50">
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-indigo-500 animate-pulse" />
                    <h3 className="text-sm font-semibold text-indigo-800">
                        {preparing ? 'Fetching Reddit thread…' : `Prompt ready: ${promptData?.title ?? ''}`}
                    </h3>
                </div>
                <button onClick={onClose}
                    className="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-indigo-100">
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {preparing ? (
                <div className="flex items-center justify-center gap-3 py-12 text-gray-500 text-sm">
                    <Spinner className="w-5 h-5" />
                    Fetching thread and building prompt…
                </div>
            ) : promptData ? (
                <div className="p-5 space-y-6">
                    {/* Step 2 */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-semibold text-gray-700">
                                Step 2 — Copy this prompt into claude.ai
                            </p>
                            <div className="flex items-center gap-2">
                                <a
                                    href="https://claude.ai"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1.5 text-xs bg-gray-900 hover:bg-gray-700 text-white px-3 py-1.5 rounded-lg font-medium transition-colors"
                                >
                                    Open claude.ai
                                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                                <button
                                    onClick={copyPrompt}
                                    className={`inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg font-medium transition-colors ${
                                        copied
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-indigo-100 hover:bg-indigo-200 text-indigo-700'
                                    }`}
                                >
                                    {copied ? (
                                        <>
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Copied!
                                        </>
                                    ) : (
                                        <>
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            Copy Prompt
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                        <textarea
                            ref={textareaRef}
                            readOnly
                            value={promptData.prompt}
                            rows={12}
                            className="w-full font-mono text-xs text-gray-700 border border-gray-200 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />
                    </div>

                    {/* Step 3 */}
                    <div className="space-y-3 pt-2 border-t border-gray-100">
                        <p className="text-sm font-semibold text-gray-700 pt-2">
                            Step 3 — Paste Claude's JSON response here
                        </p>
                        <p className="text-xs text-gray-400">
                            Claude will reply with a raw JSON object. Select all of it and paste below — markdown fences are stripped automatically.
                        </p>
                        <textarea
                            value={jsonResponse}
                            onChange={e => setJsonResponse(e.target.value)}
                            placeholder={'{\n  "title": "...",\n  "body": "...",\n  ...\n}'}
                            rows={10}
                            className="w-full font-mono text-xs text-gray-700 border border-gray-300 rounded-lg p-3 bg-white resize-y focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        />

                        {createError && (
                            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                                {createError}
                            </p>
                        )}

                        <button
                            onClick={handleCreate}
                            disabled={creating || !jsonResponse.trim()}
                            className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white
                                       px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40"
                        >
                            {creating ? (
                                <>
                                    <Spinner />
                                    Creating draft…
                                </>
                            ) : (
                                <>
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Create Draft Post
                                </>
                            )}
                        </button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

/* ── Thread Card ───────────────────────────────────────────────── */
function ThreadCard({ thread, isActive, isPreparing, disabled, onGetPrompt }) {
    return (
        <div className={`bg-white border rounded-xl p-5 transition-all ${
            isActive ? 'border-indigo-300 shadow-md ring-1 ring-indigo-100' : 'border-gray-200 hover:shadow-sm'
        }`}>
            <div className="flex items-start gap-4">
                {/* Score */}
                <div className="flex-shrink-0 flex flex-col items-center text-center min-w-[48px]">
                    <svg className="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                    <span className="text-sm font-bold text-gray-800 mt-0.5">
                        {thread.score >= 1000 ? `${(thread.score / 1000).toFixed(1)}k` : thread.score}
                    </span>
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1.5">
                        <span className="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                            r/{thread.subreddit}
                        </span>
                        <span className="text-xs text-gray-400">{timeAgo(thread.created_utc)}</span>
                        <span className="text-xs text-gray-400">· {thread.num_comments} comments</span>
                    </div>
                    <h3 className="font-semibold text-gray-900 leading-snug mb-1.5">{thread.title}</h3>
                    {thread.selftext && (
                        <p className="text-sm text-gray-500 line-clamp-2">{thread.selftext}</p>
                    )}
                </div>

                {/* Action */}
                <div className="flex-shrink-0 flex flex-col gap-2 items-end">
                    <button
                        onClick={onGetPrompt}
                        disabled={disabled}
                        className={`inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium
                                    transition-colors disabled:opacity-40 whitespace-nowrap ${
                            isActive
                                ? 'bg-indigo-100 text-indigo-700 border border-indigo-200'
                                : 'bg-indigo-600 hover:bg-indigo-700 text-white'
                        }`}
                    >
                        {isPreparing ? (
                            <>
                                <Spinner />
                                Loading…
                            </>
                        ) : isActive ? (
                            <>
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Prompt Ready ↑
                            </>
                        ) : (
                            <>
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Get Prompt
                            </>
                        )}
                    </button>
                    <a
                        href={thread.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-xs text-gray-400 hover:text-indigo-600 transition-colors"
                    >
                        View on Reddit →
                    </a>
                </div>
            </div>
        </div>
    );
}

function Spinner({ className = 'w-4 h-4' }) {
    return (
        <svg className={`animate-spin ${className}`} fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    );
}
