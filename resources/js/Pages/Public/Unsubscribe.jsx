import { Head } from '@inertiajs/react';
import { useState } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function Unsubscribe({ status }) {
    const [email, setEmail]     = useState('');
    const [result, setResult]   = useState(status ?? null); // 'success' | 'not_found' | 'error' | null
    const [loading, setLoading] = useState(false);

    async function handleSubmit(e) {
        e.preventDefault();
        if (loading) return;
        setLoading(true);
        setResult(null);

        try {
            const res = await fetch(route('unsubscribe.email'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ email }),
            });

            if (res.ok) {
                setResult('success');
                setEmail('');
            } else if (res.status === 404) {
                setResult('not_found');
            } else {
                setResult('error');
            }
        } catch {
            setResult('error');
        } finally {
            setLoading(false);
        }
    }

    return (
        <PublicLayout>
            <Head title="Unsubscribe — GadgetDrop" />

            <div className="min-h-[60vh] flex items-center justify-center px-4 py-20 bg-slate-50">
                <div className="w-full max-w-md">
                    {result === 'success' ? (
                        /* ── Success state ── */
                        <div className="text-center space-y-4">
                            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-2">
                                <svg className="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </div>
                            <h1 className="text-2xl font-extrabold text-gray-900">You're unsubscribed</h1>
                            <p className="text-gray-500 text-sm leading-relaxed">
                                You've been removed from the GadgetDrop list.<br />
                                No more emails from us — we promise.
                            </p>
                            <a href={route('home')}
                                className="inline-flex items-center gap-1.5 mt-4 text-sm text-indigo-600 hover:text-indigo-700 font-semibold transition-colors">
                                ← Back to GadgetDrop
                            </a>
                        </div>
                    ) : (
                        /* ── Form state ── */
                        <div>
                            <div className="text-center mb-8">
                                <h1 className="text-3xl font-extrabold text-gray-900 mb-2">Unsubscribe</h1>
                                <p className="text-gray-500 text-sm">
                                    Enter the email address you signed up with and we'll remove it immediately.
                                </p>
                            </div>

                            <form onSubmit={handleSubmit}
                                className="bg-white border border-gray-100 rounded-2xl shadow-sm p-8 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Email address
                                    </label>
                                    <input
                                        type="email"
                                        value={email}
                                        onChange={(e) => { setEmail(e.target.value); setResult(null); }}
                                        placeholder="your@email.com"
                                        required
                                        autoFocus
                                        className="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>

                                {result === 'not_found' && (
                                    <p className="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5">
                                        That email isn't on our list — you may already be unsubscribed.
                                    </p>
                                )}
                                {result === 'error' && (
                                    <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5">
                                        Something went wrong — please try again.
                                    </p>
                                )}

                                <button
                                    type="submit"
                                    disabled={loading}
                                    className="w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-semibold
                                               text-sm rounded-lg transition-colors disabled:opacity-50"
                                >
                                    {loading ? 'Removing…' : 'Unsubscribe me'}
                                </button>
                            </form>

                            <p className="text-center mt-6 text-xs text-gray-400">
                                Changed your mind?{' '}
                                <a href={route('home')} className="text-indigo-600 hover:underline font-medium">
                                    Go back to GadgetDrop
                                </a>
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}
