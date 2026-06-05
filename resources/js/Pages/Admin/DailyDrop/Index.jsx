import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function DailyDropIndex({ authors = [] }) {
    const [jsonResponse, setJsonResponse] = useState('');
    const [userId,       setUserId]       = useState('');
    const [importing,    setImporting]    = useState(false);
    const [createdPosts, setCreatedPosts] = useState([]);
    const [error,        setError]        = useState(null);

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
            <div>
                <h2 className="text-xl font-semibold text-gray-800">Daily Drop Importer</h2>
                <p className="text-sm text-gray-400 mt-0.5">
                    Paste the JSON output from /daily-drop to create draft posts automatically.
                </p>
            </div>
        }>
            <Head title="Daily Drop Importer" />

            <div className="py-8 px-4 max-w-3xl mx-auto space-y-6">

                <section className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h3 className="text-sm font-semibold text-gray-800">Import Posts from JSON</h3>
                    </div>

                    <form onSubmit={handleImport} className="p-6 space-y-5">

                        {/* Author override */}
                        <div className="space-y-1.5">
                            <label className="block text-sm font-medium text-gray-700">
                                Author Override
                                <span className="ml-1.5 text-xs font-normal text-gray-400">
                                    (optional: leave blank to use author_name from JSON, or current user as fallback)
                                </span>
                            </label>
                            <select
                                value={userId}
                                onChange={e => setUserId(e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-amber-500 focus:border-amber-500">
                                <option value="">Use author_name from JSON</option>
                                {authors.map(a => (
                                    <option key={a.id} value={a.id}>{a.name}</option>
                                ))}
                            </select>
                        </div>

                        {/* JSON paste area */}
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
                                placeholder={'{\n  "title": "...",\n  "excerpt": "...",\n  "body": "...",\n  "type": "article",\n  "author_name": "...",\n  "category_name": "...",\n  "tag_names": [...],\n  "product_asin": "B0XXXXXXXXX",\n  "rating": 4.5,\n  "pros": [...],\n  "cons": [...],\n  "seo": { ... }\n}'}
                                rows={20}
                                required
                                className="w-full font-mono text-xs text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-amber-400"
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
                            className="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white
                                       px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40">
                            {importing ? (
                                <><Spinner />Importing drafts…</>
                            ) : (
                                <>
                                    <DownloadIcon className="w-4 h-4" />
                                    Import as Drafts
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
                                                   text-amber-600 hover:text-amber-800 transition-colors">
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

function DownloadIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
    );
}

function CheckIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
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
