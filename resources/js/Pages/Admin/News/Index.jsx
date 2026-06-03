import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function NewsIndex({ authors = [] }) {
    // Step 1 — Prompt builder
    const [sourceUrls,       setSourceUrls]       = useState('');
    const [context,          setContext]           = useState('');
    const [trendingKeywords, setTrendingKeywords]  = useState('');
    const [userId,           setUserId]            = useState('');
    const [building,         setBuilding]          = useState(false);
    const [builtPrompt,      setBuiltPrompt]       = useState('');
    const [copied,           setCopied]            = useState(false);

    // Step 2 — Draft creation
    const [sourceUrl,    setSourceUrl]    = useState('');
    const [jsonResponse, setJsonResponse] = useState('');
    const [creating,     setCreating]     = useState(false);

    const [error, setError] = useState(null);

    async function handleBuildPrompt(e) {
        e.preventDefault();
        if (!sourceUrls.trim() || !userId) return;
        setBuilding(true);
        setError(null);
        setBuiltPrompt('');

        try {
            const { data } = await axios.post(route('admin.news.prompt'), {
                source_urls:       sourceUrls.trim(),
                context:           context.trim(),
                trending_keywords: trendingKeywords.trim(),
                user_id:           userId,
            });
            setBuiltPrompt(data.prompt);
            // Pre-fill source_url with first URL from the list
            const firstUrl = sourceUrls.trim().split('\n').find(l => l.trim());
            if (firstUrl && !sourceUrl) setSourceUrl(firstUrl.trim());
        } catch (err) {
            setError(err.response?.data?.error ?? err.response?.data?.message ?? 'Failed to build prompt.');
        } finally {
            setBuilding(false);
        }
    }

    async function handleCopy() {
        await navigator.clipboard.writeText(builtPrompt);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    async function handleCreate(e) {
        e.preventDefault();
        if (!jsonResponse.trim()) return;
        setCreating(true);
        setError(null);

        try {
            const { data } = await axios.post(route('admin.news.generate'), {
                source_url:    sourceUrl.trim(),
                json_response: jsonResponse.trim(),
                user_id:       userId || null,
            });
            window.location.href = data.redirect;
        } catch (err) {
            setError(err.response?.data?.error ?? err.response?.data?.message ?? 'Failed to create draft.');
            setCreating(false);
        }
    }

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-gray-800">Tech News Generator</h2>
                    <p className="text-sm text-gray-400 mt-0.5">
                        Build a Claude prompt from source URLs, then paste the JSON response to create a draft post.
                    </p>
                </div>
                <a href="https://claude.ai" target="_blank" rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Open claude.ai
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            </div>
        }>
            <Head title="Tech News Generator" />

            <div className="py-8 px-4 max-w-3xl mx-auto space-y-6">

                {/* ── Step 1: Build Prompt ── */}
                <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span className="w-6 h-6 rounded-full bg-rose-100 text-rose-600 text-xs font-bold flex items-center justify-center shrink-0">1</span>
                        <h3 className="text-sm font-semibold text-gray-800">Build the Claude Prompt</h3>
                    </div>

                    <form onSubmit={handleBuildPrompt} className="p-6 space-y-5">

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Author
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(required — voice is woven into the prompt)</span>
                            </label>
                            <select
                                value={userId}
                                onChange={e => setUserId(e.target.value)}
                                required
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-rose-500 focus:border-rose-500">
                                <option value="">— Select author —</option>
                                {authors.map(a => (
                                    <option key={a.id} value={a.id}>{a.name}</option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Source URLs
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(required — one per line)</span>
                            </label>
                            <textarea
                                value={sourceUrls}
                                onChange={e => setSourceUrls(e.target.value)}
                                placeholder={"https://techcrunch.com/...\nhttps://theverge.com/..."}
                                rows={4}
                                required
                                className="w-full font-mono text-xs text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-rose-400"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Google Trending Topics
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(optional — paste today's trends for keyword weaving)</span>
                            </label>
                            <textarea
                                value={trendingKeywords}
                                onChange={e => setTrendingKeywords(e.target.value)}
                                placeholder={"iPhone 17 Pro\nAI chip\nSamsung Galaxy S25\n..."}
                                rows={4}
                                className="w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-rose-400"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Editor Notes
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(optional — angle, context, things to emphasize)</span>
                            </label>
                            <textarea
                                value={context}
                                onChange={e => setContext(e.target.value)}
                                placeholder="e.g. Focus on the pricing angle. Audience skews budget-conscious. Don't bury the lead."
                                rows={3}
                                className="w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-rose-400"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={building || !sourceUrls.trim() || !userId}
                            className="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white
                                       px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
                            {building ? <><Spinner />Building prompt…</> : <>
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Build Prompt
                            </>}
                        </button>
                    </form>

                    {builtPrompt && (
                        <div className="border-t border-gray-100 p-6 space-y-3">
                            <div className="flex items-center justify-between">
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Generated Prompt</p>
                                <button
                                    onClick={handleCopy}
                                    className={`inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors
                                        ${copied
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-gray-100 hover:bg-gray-200 text-gray-600'}`}>
                                    {copied ? (
                                        <><CheckIcon className="w-3.5 h-3.5" />Copied!</>
                                    ) : (
                                        <><CopyIcon className="w-3.5 h-3.5" />Copy to clipboard</>
                                    )}
                                </button>
                            </div>
                            <textarea
                                readOnly
                                value={builtPrompt}
                                rows={14}
                                className="w-full font-mono text-xs text-gray-600 border border-gray-200 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none"
                            />
                            <p className="text-xs text-gray-400">
                                Copy this prompt → paste into claude.ai → get JSON back → continue to Step 2 below.
                            </p>
                        </div>
                    )}
                </section>

                {/* ── Step 2: Paste JSON ── */}
                <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span className="w-6 h-6 rounded-full bg-rose-100 text-rose-600 text-xs font-bold flex items-center justify-center shrink-0">2</span>
                        <h3 className="text-sm font-semibold text-gray-800">Paste Claude's JSON &amp; Create Draft</h3>
                    </div>

                    <form onSubmit={handleCreate} className="p-6 space-y-5">

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Primary Source URL
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(stored on the post for attribution)</span>
                            </label>
                            <input
                                type="url"
                                value={sourceUrl}
                                onChange={e => setSourceUrl(e.target.value)}
                                placeholder="https://techcrunch.com/..."
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-rose-500 focus:border-rose-500"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Claude's JSON Response
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(paste the full output from claude.ai)</span>
                            </label>
                            <textarea
                                value={jsonResponse}
                                onChange={e => setJsonResponse(e.target.value)}
                                placeholder={'{\n  "title": "...",\n  "excerpt": "...",\n  "body": "...",\n  "tags": [...],\n  "focus_keyword": "...",\n  "meta_title": "...",\n  "meta_description": "..."\n}'}
                                rows={16}
                                required
                                className="w-full font-mono text-xs text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-rose-400"
                            />
                        </div>

                        {error && (
                            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                                {error}
                            </p>
                        )}

                        <button
                            type="submit"
                            disabled={creating || !jsonResponse.trim()}
                            className="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white
                                       px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
                            {creating ? (
                                <><Spinner />Creating draft…</>
                            ) : (
                                <>
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Create Draft Post
                                </>
                            )}
                        </button>
                    </form>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Spinner() {
    return (
        <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    );
}

function CopyIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
    );
}

function CheckIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    );
}
