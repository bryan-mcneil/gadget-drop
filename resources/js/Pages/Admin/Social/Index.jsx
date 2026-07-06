import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const PLATFORM_STYLES = {
    bluesky: 'bg-sky-100 text-sky-700',
    facebook: 'bg-blue-100 text-blue-700',
    x: 'bg-gray-200 text-gray-700',
    youtube: 'bg-red-100 text-red-700',
};

function PlatformChip({ platform }) {
    return (
        <span className={`text-[11px] font-bold px-2 py-0.5 rounded-full capitalize ${PLATFORM_STYLES[platform] ?? 'bg-gray-100 text-gray-600'}`}>
            {platform}
        </span>
    );
}

function QueueCard({ row }) {
    const [busy, setBusy] = useState(false);
    const [copied, setCopied] = useState(false);
    const [postedUrl, setPostedUrl] = useState('');

    function copy() {
        navigator.clipboard.writeText(row.body).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    function markPosted() {
        setBusy(true);
        router.post(route('admin.social.posted', row.id), { external_url: postedUrl || null }, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    function skip() {
        setBusy(true);
        router.post(route('admin.social.skip', row.id), {}, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    return (
        <div className="bg-white rounded-xl shadow p-4 space-y-3">
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2 min-w-0">
                    <PlatformChip platform={row.platform} />
                    <p className="font-medium text-gray-900 truncate">{row.post_title}</p>
                </div>
                <span className="text-xs text-gray-400 whitespace-nowrap">{row.chars} chars</span>
            </div>

            {row.last_error && (
                <p className="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                    API failed {row.attempts}x — posting is now manual for this one. Last error: {row.last_error}
                </p>
            )}

            <pre className="whitespace-pre-wrap break-words font-sans text-sm text-gray-700 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2">{row.body}</pre>

            <div className="flex flex-wrap items-center gap-2">
                <button onClick={copy} disabled={busy}
                    className="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-indigo-700 disabled:opacity-40">
                    {copied ? 'Copied ✓' : 'Copy text'}
                </button>
                <a href={row.compose_url} target="_blank" rel="noreferrer noopener"
                    className="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-gray-50">
                    Open composer ↗
                </a>
                <input type="url" placeholder="Live post URL (optional)" value={postedUrl}
                    onChange={(e) => setPostedUrl(e.target.value)}
                    className="flex-1 min-w-[12rem] rounded-lg border-gray-300 text-xs py-1.5" />
                <button onClick={markPosted} disabled={busy}
                    className="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-green-700 disabled:opacity-40">
                    Mark posted
                </button>
                <button onClick={skip} disabled={busy}
                    className="text-gray-400 hover:text-gray-600 px-2 py-1.5 text-xs underline disabled:opacity-40">
                    Skip
                </button>
            </div>
        </div>
    );
}

export default function SocialIndex({ queue, pending, recent, enabled }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Social Queue</h2>}>
            <Head title="Social Queue" />

            <div className="py-8 px-4 max-w-4xl mx-auto space-y-4">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>
                )}

                {!enabled && (
                    <div className="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
                        The social pipeline is off (<code className="font-mono">SOCIAL_ENABLED=false</code>) — publishing a
                        post won't queue anything here until it's enabled.
                    </div>
                )}

                <div className="bg-indigo-50 border border-indigo-100 text-indigo-900 px-4 py-3 rounded-lg text-sm">
                    Each card is a fully composed post. <strong>Copy text</strong>, <strong>Open composer ↗</strong>,
                    paste, publish on the platform, then <strong>Mark posted</strong> (paste the live URL if you have it,
                    so history stays complete). Bluesky's composer even pre-fills the text for you.
                </div>

                {queue.length === 0 ? (
                    <div className="bg-white rounded-xl shadow px-4 py-8 text-center text-sm text-gray-400">
                        Nothing waiting for a manual post. 🎉
                    </div>
                ) : (
                    queue.map((row) => <QueueCard key={row.id} row={row} />)
                )}

                {pending.length > 0 && (
                    <div className="bg-white rounded-xl shadow overflow-hidden">
                        <p className="px-4 pt-4 text-xs font-bold uppercase text-gray-400">Waiting on the hourly publisher</p>
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-gray-100">
                                {pending.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3"><PlatformChip platform={row.platform} /></td>
                                        <td className="px-4 py-3 text-gray-700">{row.post_title}</td>
                                        <td className="px-4 py-3 text-right text-xs text-gray-400">
                                            {row.attempts > 0 ? `${row.attempts} failed attempt${row.attempts === 1 ? '' : 's'}` : 'queued'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {recent.length > 0 && (
                    <div className="bg-white rounded-xl shadow overflow-hidden">
                        <p className="px-4 pt-4 text-xs font-bold uppercase text-gray-400">Recent history</p>
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-gray-100">
                                {recent.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3"><PlatformChip platform={row.platform} /></td>
                                        <td className="px-4 py-3 text-gray-700 max-w-[18rem] truncate">{row.post_title}</td>
                                        <td className="px-4 py-3">
                                            <span className={`text-[11px] font-bold px-2 py-0.5 rounded-full ${row.status === 'posted' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right text-xs text-gray-400 whitespace-nowrap">
                                            {row.external_url ? (
                                                <a href={row.external_url} target="_blank" rel="noreferrer noopener" className="underline hover:text-gray-600">view ↗</a>
                                            ) : (
                                                row.posted_at ? new Date(row.posted_at).toLocaleDateString() : ''
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
