import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function TechTipsIndex() {
    const [sourceUrl,    setSourceUrl]    = useState('');
    const [jsonResponse, setJsonResponse] = useState('');
    const [creating,     setCreating]     = useState(false);
    const [error,        setError]        = useState(null);

    async function handleCreate(e) {
        e.preventDefault();
        if (!jsonResponse.trim()) return;
        setCreating(true);
        setError(null);

        try {
            const { data } = await axios.post(route('admin.tech-tips.generate'), {
                source_url:    sourceUrl.trim(),
                json_response: jsonResponse.trim(),
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
                    <h2 className="text-xl font-semibold text-gray-800">Tech Tips Generator</h2>
                    <p className="text-sm text-gray-400 mt-0.5">
                        Paste Claude's JSON response to create a draft post.
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
            <Head title="Tech Tips Generator" />

            <div className="py-8 px-4 max-w-3xl mx-auto">
                <form onSubmit={handleCreate} className="bg-white border border-gray-200 rounded-xl p-6 space-y-5">

                    <div className="space-y-1.5">
                        <label className="block text-sm font-medium text-gray-700">
                            Reddit Source URL
                            <span className="ml-1.5 text-xs font-normal text-gray-400">(optional — adds attribution link to the post)</span>
                        </label>
                        <input
                            type="url"
                            value={sourceUrl}
                            onChange={e => setSourceUrl(e.target.value)}
                            placeholder="https://www.reddit.com/r/techsupport/comments/…"
                            className="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
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
                            className="w-full font-mono text-xs text-gray-700 border border-gray-300 rounded-lg p-3 bg-gray-50 resize-y focus:outline-none focus:ring-2 focus:ring-indigo-400"
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
                        className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white
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
