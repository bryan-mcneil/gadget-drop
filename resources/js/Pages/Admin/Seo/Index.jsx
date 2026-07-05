import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const KIND_LABELS = {
    striking_distance: 'Striking distance',
    ctr_fix: 'CTR fix',
    content_gap: 'Content gap',
    decay: 'Decay',
    cannibalization: 'Cannibalization',
    rising: 'Rising',
    bing_gap: 'Bing gap',
};

const STATUSES = ['open', 'planned', 'done', 'dismissed', 'all'];

function Panel({ title, children, right }) {
    return (
        <div className="bg-white rounded-xl shadow overflow-hidden">
            <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 className="font-semibold text-gray-800 text-sm">{title}</h3>
                {right}
            </div>
            {children}
        </div>
    );
}

function TrendPanel({ trend }) {
    const hasData = trend.lines.some((l) => l.clicks_28 > 0);

    return (
        <Panel title="Clicks — last 90 days">
            <div className="p-4">
                {hasData ? (
                    <>
                        <svg viewBox={`0 0 ${trend.width} ${trend.height}`} preserveAspectRatio="none"
                            className="w-full h-40 border border-gray-100 rounded-lg bg-gray-50">
                            {trend.lines.map((l) => (
                                <polyline key={l.label} points={l.points} fill="none" stroke={l.color} strokeWidth="2"
                                    vectorEffect="non-scaling-stroke" />
                            ))}
                        </svg>
                        <div className="flex flex-wrap gap-4 mt-3 text-xs">
                            {trend.lines.map((l) => (
                                <span key={l.label} className="inline-flex items-center gap-1.5 text-gray-600">
                                    <span className="w-3 h-1.5 rounded-full" style={{ background: l.color }} />
                                    {l.label}: <strong>{l.clicks_28}</strong> clicks/28d
                                </span>
                            ))}
                        </div>
                    </>
                ) : (
                    <p className="text-center text-sm text-gray-400 py-10">No search data synced yet. Run <code>search:sync</code> once Search Console is connected.</p>
                )}
            </div>
        </Panel>
    );
}

function OpportunityRow({ o }) {
    function mark(status) {
        router.post(route('admin.seo.opportunities.update', o.id), { status }, { preserveScroll: true });
    }

    const target = o.post ? o.post.title : (o.query ?? '—');
    const phrasings = o.evidence?.phrasings?.length ?? 0;

    return (
        <tr className="hover:bg-gray-50 align-top">
            <td className="px-4 py-3">
                <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                    {KIND_LABELS[o.kind] ?? o.kind}
                </span>
            </td>
            <td className="px-4 py-3">
                <p className="font-medium text-gray-900 max-w-[22rem] truncate">{target}</p>
                {o.query && o.post && <p className="text-xs text-gray-400 truncate max-w-[22rem]">“{o.query}”</p>}
                {phrasings > 1 && <p className="text-xs text-gray-400">{phrasings} phrasings</p>}
            </td>
            <td className="px-4 py-3 font-mono text-gray-700">{Math.round(o.score)}</td>
            <td className="px-4 py-3 text-xs text-gray-400">{o.last_seen ?? '—'}</td>
            <td className="px-4 py-3 text-right whitespace-nowrap space-x-1">
                {o.status !== 'planned' && <button onClick={() => mark('planned')} className="text-xs px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50">Plan</button>}
                {o.status !== 'done' && <button onClick={() => mark('done')} className="text-xs px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50">Done</button>}
                {o.status !== 'dismissed' && <button onClick={() => mark('dismissed')} className="text-xs px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50 text-red-600">Dismiss</button>}
                {o.status !== 'open' && <button onClick={() => mark('open')} className="text-xs px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50">Reopen</button>}
            </td>
        </tr>
    );
}

export default function SeoIndex({ filters, trend, opportunities, coverage, movers, submissions }) {
    const { flash } = usePage().props;

    function filterLink(patch) {
        const next = { status: filters.status, kind: filters.kind, ...patch };
        const params = {};
        if (next.status && next.status !== 'open') params.status = next.status; // 'open' is the controller default
        if (next.kind) params.kind = next.kind;
        return route('admin.seo.index', params);
    }

    function reping(id) {
        router.post(route('admin.seo.reping', id), {}, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Search Intel</h2>}>
            <Head title="Search Intel" />

            <div className="py-8 px-4 max-w-6xl mx-auto space-y-6">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>
                )}

                <TrendPanel trend={trend} />

                <Panel
                    title="Opportunities"
                    right={
                        <div className="flex flex-wrap gap-1 text-xs">
                            {STATUSES.map((s) => (
                                <Link key={s} href={filterLink({ status: s })}
                                    className={`px-2 py-1 rounded border ${filters.status === s ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'}`}>
                                    {s}
                                </Link>
                            ))}
                        </div>
                    }
                >
                    <div className="px-4 py-2 border-b border-gray-100 flex flex-wrap gap-1 text-xs">
                        <Link href={filterLink({ kind: null })}
                            className={`px-2 py-1 rounded border ${!filters.kind ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'}`}>
                            all kinds
                        </Link>
                        {filters.kinds.map((k) => (
                            <Link key={k} href={filterLink({ kind: k })}
                                className={`px-2 py-1 rounded border ${filters.kind === k ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'}`}>
                                {KIND_LABELS[k] ?? k}
                            </Link>
                        ))}
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th className="px-4 py-2 text-left">Kind</th>
                                <th className="px-4 py-2 text-left">Target</th>
                                <th className="px-4 py-2 text-left">Score</th>
                                <th className="px-4 py-2 text-left">Seen</th>
                                <th className="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {opportunities.map((o) => <OpportunityRow key={o.id} o={o} />)}
                        </tbody>
                    </table>
                    {opportunities.length === 0 && (
                        <p className="px-4 py-8 text-center text-sm text-gray-400">No opportunities for this filter. Run <code>search:mine</code> after a sync.</p>
                    )}
                </Panel>

                <div className="grid md:grid-cols-2 gap-6">
                    <Panel title={`Index coverage (${coverage.length} not confirmed)`}>
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-gray-100">
                                {coverage.map((p) => (
                                    <tr key={p.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-900 max-w-[18rem] truncate">{p.title}</p>
                                            <p className="text-xs text-gray-400">{p.days}d old · {p.verdict ?? 'never checked'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button onClick={() => reping(p.id)}
                                                className="text-xs px-2 py-1 rounded bg-white border border-gray-300 hover:bg-gray-50">Re-ping</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {coverage.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400">All recent posts confirmed indexed. 🎉</p>}
                    </Panel>

                    <Panel title="Movers — week over week">
                        <div className="p-4 text-sm space-y-3">
                            <div>
                                <p className="text-xs font-semibold text-green-600 uppercase mb-1">Gainers</p>
                                {movers.gainers.length ? movers.gainers.map((m) => (
                                    <div key={m.id} className="flex justify-between gap-2">
                                        <span className="truncate text-gray-700">{m.title}</span>
                                        <span className="font-mono text-green-600">+{m.delta}</span>
                                    </div>
                                )) : <p className="text-gray-400 text-xs">No data.</p>}
                            </div>
                            <div>
                                <p className="text-xs font-semibold text-red-600 uppercase mb-1">Losers</p>
                                {movers.losers.length ? movers.losers.map((m) => (
                                    <div key={m.id} className="flex justify-between gap-2">
                                        <span className="truncate text-gray-700">{m.title}</span>
                                        <span className="font-mono text-red-600">{m.delta}</span>
                                    </div>
                                )) : <p className="text-gray-400 text-xs">No data.</p>}
                            </div>
                        </div>
                    </Panel>
                </div>

                <Panel title="Recent submissions">
                    <table className="w-full text-sm">
                        <tbody className="divide-y divide-gray-100">
                            {submissions.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 text-gray-600 truncate max-w-[24rem]">{s.url}</td>
                                    <td className="px-4 py-2 text-xs text-gray-400">{s.engine} · {s.trigger}</td>
                                    <td className="px-4 py-2 text-xs font-mono text-gray-500">{s.response_code ?? '—'}</td>
                                    <td className="px-4 py-2 text-xs text-gray-400 text-right">{s.submitted_at?.slice(0, 10)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {submissions.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400">No submissions logged yet.</p>}
                </Panel>
            </div>
        </AuthenticatedLayout>
    );
}
