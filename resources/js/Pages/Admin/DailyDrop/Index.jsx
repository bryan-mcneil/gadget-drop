import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function DailyDropIndex({ authors = [] }) {
    // Step 1 -- Prompt builder
    const [userId,        setUserId]        = useState('');
    const [productUrl,    setProductUrl]    = useState('');
    const [sourceContent, setSourceContent] = useState('');
    const [editorNotes,   setEditorNotes]   = useState('');
    const [building,      setBuilding]      = useState(false);
    const [builtPrompt,   setBuiltPrompt]   = useState('');
    const [parsedAsin,    setParsedAsin]    = useState('');
    const [copied,        setCopied]        = useState(false);

    // Step 2 -- Draft creation
    const [jsonResponse, setJsonResponse] = useState('');
    const [importing,    setImporting]    = useState(false);
    const [createdPosts, setCreatedPosts] = useState([]);

    const [error, setError] = useState(null);

    async function handleBuildPrompt(e) {
        e.preventDefault();
        if (!productUrl.trim() || !userId) return;
        setBuilding(true);
        setError(null);
        setBuiltPrompt('');
        setParsedAsin('');

        try {
            const { data } = await axios.post(route('admin.daily-drop.prompt'), {
                user_id:        userId,
                product_url:    productUrl.trim(),
                source_content: sourceContent.trim(),
                editor_notes:   editorNotes.trim(),
            });
            setBuiltPrompt(data.prompt);
            setParsedAsin(data.asin);
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

    async function handleImport(e) {
        e.preventDefault();
        if (!jsonResponse.trim()) return;
        setImporting(true);
        setError(null);
        setCreatedPosts([]);

        try {
            const { data } = await axios.post(route('admin.daily-drop.generate'), {
                json_response: jsonResponse.trim(),
                user_id:       userId || null,
            });
            setCreatedPosts(data.posts);
        } catch (err) {
            setError(err.response?.data?.error ?? err.response?.data?.message ?? 'Import failed.');
        } finally {
            setImporting(false);
        }
    }

    const postCount = (() => {
        const t = jsonResponse.trim();
        if (!t) return null;
        try {
            const d = JSON.parse(t);
            if (Array.isArray(d)) return d.length;
            if (d && d.title) return 1;
        } catch { /* ignore */ }
        return null;
    })();

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-gray-800">Daily Drop Generator</h2>
                    <p className="text-sm text-gray-400 mt-0.5">
                        Build a Claude prompt for a targeted product, then paste the JSON response to create a draft.
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
            <Head title="Daily Drop Generator" />

            <div className="py-8 px-4 max-w-3xl mx-auto space-y-6">

                {/* Step 1: Build Prompt */}
                <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span className="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center shrink-0">1</span>
                        <h3 className="text-sm font-semibold text-gray-800">Build the Claude Prompt</h3>
                    </div>

                    <form onSubmit={handleBuildPrompt} className="p-6 space-y-5">

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Author
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(required: voice is woven into the prompt)</span>
                            </label>
                            <select
                                value={userId}
                                onChange={e => setUserId(e.target.value)}
                                required
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select author</option>
                                {authors.map(a => (
                                    <option key={a.id} value={a.id}>{a.name}</option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Amazon Product URL
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(required: ASIN is parsed automatically)</span>
                            </label>
                            <input
                                type="text"
                                value={productUrl}
                                onChange={e => setProductUrl(e.target.value)}
                                placeholder="https://www.amazon.com/dp/B0XXXXXXXXX"
                                required
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            {parsedAsin && (
                                <p className="text-xs text-indigo-600 font-medium">
                                    ASIN detected: {parsedAsin}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Source Content
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(paste product details, reviews, or specs)</span>
                            </label>
                            <textarea
                                value={sourceContent}
                                onChange={e => setSourceContent(e.target.value)}
                                placeholder="Paste the Amazon listing description, customer reviews, spec sheet, or any article about this product..."
                                rows={8}
                                className="w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Editor Notes
                                <span className="ml-1.5 text-xs font-normal text-gray-400">(optional: angle, audience, things to emphasize)</span>
                            </label>
                            <textarea
                                value={editorNotes}
                                onChange={e => setEditorNotes(e.target.value)}
                                placeholder="e.g. Budget-conscious audience. Emphasize the price drop. Compare to the previous model."
                                rows={3}
                                className="w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            />
                        </div>

                        {error && (
                            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                                {error}
                            </p>
                        )}

                        <button
                            type="submit"
                            disabled={building || !productUrl.trim() || !userId}
                            className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                                       px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
                            {building ? <><Spinner />Building prompt...</> : <>
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
                                    {copied
                                        ? <><CheckIcon className="w-3.5 h-3.5" />Copied!</>
                                        : <><CopyIcon className="w-3.5 h-3.5" />Copy to clipboard</>}
                                </button>
                            </div>
                            <textarea
                                readOnly
                                value={builtPrompt}
                                rows={14}
                                className="w-full font-mono text-xs text-gray-600 border border-gray-200 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none"
                            />
                            <p className="text-xs text-gray-400">
                                Copy this prompt, paste into claude.ai, get JSON back, then continue to Step 2.
                            </p>
                        </div>
                    )}
                </section>

                {/* Step 2: Paste JSON */}
                <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span className="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center shrink-0">2</span>
                        <h3 className="text-sm font-semibold text-gray-800">Paste Claude's JSON &amp; Create Draft</h3>
                    </div>

                    <form onSubmit={handleImport} className="p-6 space-y-5">

                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                JSON Output
                                <span className="ml-1.5 text-xs font-normal text-gray-400">
                                    (paste a single post object or an array of post objects)
                                </span>
                            </label>
                            <textarea
                                value={jsonResponse}
                                onChange={e => setJsonResponse(e.target.value)}
                                placeholder={'{\n  "title": "...",\n  "excerpt": "...",\n  "body": "...",\n  "type": "article",\n  "author_name": "...",\n  "category_name": "...",\n  "tag_names": [...],\n  "product_asin": "B0XXXXXXXXX",\n  "rating": 4.5,\n  "pros": [...],\n  "cons": [...],\n  "seo": { "score": 85, "meta_title": "...", "meta_description": "...", "focus_keyword": "...", "slug": "..." }\n}'}
                                rows={20}
                                required
                                className="w-full font-mono text-xs text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-indigo-400"
                            />
                            {postCount !== null && (
                                <p className="text-xs text-gray-400">
                                    {postCount === 1 ? '1 post detected' : `${postCount} posts detected`}
                                </p>
                            )}
                        </div>

                        {error && (
                            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                                {error}
                            </p>
                        )}

                        <button
                            type="submit"
                            disabled={importing || !jsonResponse.trim()}
                            className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white
                                       px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
                            {importing ? (
                                <><Spinner />Importing draft...</>
                            ) : (
                                <>
                                    <DownloadIcon className="w-4 h-4" />
                                    Import as Draft
                                </>
                            )}
                        </button>
                    </form>
                </section>

                {/* Results */}
                {createdPosts.length > 0 && (
                    <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                            <span className="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <CheckIcon className="w-3 h-3" />
                            </span>
                            <h3 className="text-sm font-semibold text-gray-800">
                                {createdPosts.length === 1
                                    ? '1 draft created'
                                    : `${createdPosts.length} drafts created`}
                            </h3>
                        </div>
                        <ul className="divide-y divide-gray-100">
                            {createdPosts.map(post => (
                                <li key={post.id} className="px-6 py-3 flex items-center justify-between gap-4">
                                    <span className="text-sm text-gray-700 truncate">{post.title}</span>
                                    <a
                                        href={route('admin.posts.edit', post.id)}
                                        className="shrink-0 inline-flex items-center gap-1.5 text-xs font-medium
                                                   text-indigo-600 hover:text-indigo-800 transition-colors">
                                        Edit draft
                                        <ArrowRightIcon className="w-3.5 h-3.5" />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
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

function DownloadIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
    );
}

function ArrowRightIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
    );
}
