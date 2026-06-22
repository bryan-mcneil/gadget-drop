import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ stats, recentPosts, dropPrice }) {
    const statCards = [
        { label: 'Total Posts', value: stats.posts, href: route('admin.posts.index') },
        { label: 'Published', value: stats.published, href: route('admin.posts.index') },
        { label: 'Products', value: stats.products, href: route('admin.products.index') },
        { label: "Clicks Today", value: stats.clicks_today, href: '#' },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Admin Dashboard</h2>}>
            <Head title="Admin Dashboard" />

            <div className="py-8 px-4 max-w-7xl mx-auto space-y-8">
                {/* Stat cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {statCards.map((s) => (
                        <Link key={s.label} href={s.href}
                            className="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-sm transition">
                            <p className="text-sm text-gray-500">{s.label}</p>
                            <p className="text-3xl font-bold text-gray-900 mt-1">{s.value}</p>
                        </Link>
                    ))}
                </div>

                {/* Drop Price — read-only daily-game summary (admin-only, so the answer price is shown) */}
                <DropPricePanel dropPrice={dropPrice} />

                {/* Quick actions */}
                <div className="flex gap-3">
                    <Link href={route('admin.posts.create')}
                        className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
                        + New Post
                    </Link>
                    <Link href={route('admin.products.create')}
                        className="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                        + Add Product
                    </Link>
                    <Link href={route('admin.categories.index')}
                        className="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                        Manage Categories
                    </Link>
                    <Link href={route('admin.tags.index')}
                        className="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                        Manage Tags
                    </Link>
                </div>

                {/* Recent posts */}
                <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 className="font-semibold text-gray-800">Recent Posts</h3>
                        <Link href={route('admin.posts.index')} className="text-sm text-indigo-600 hover:underline">View all</Link>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th className="px-6 py-3 text-left">Title</th>
                                <th className="px-6 py-3 text-left">Author</th>
                                <th className="px-6 py-3 text-left">Status</th>
                                <th className="px-6 py-3 text-left">Published</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {recentPosts.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3 font-medium text-gray-900">
                                        <Link href={route('admin.posts.edit', p.id)} className="hover:text-indigo-600">{p.title}</Link>
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">{p.author}</td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={p.status} />
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">{p.published_at ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function StatusBadge({ status }) {
    const colors = {
        published: 'bg-green-100 text-green-700',
        draft: 'bg-gray-100 text-gray-600',
        scheduled: 'bg-yellow-100 text-yellow-700',
    };
    return (
        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${colors[status] ?? colors.draft}`}>
            {status}
        </span>
    );
}

const money = (n) =>
    typeof n === 'number' ? `$${n.toLocaleString('en-US')}` : '—';

function DropPricePanel({ dropPrice }) {
    const today = dropPrice?.today ?? null;
    const upcoming = dropPrice?.upcoming ?? null;

    return (
        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 className="font-semibold text-gray-800">💰 Drop Price</h3>
                <a href="/" target="_blank" rel="noopener noreferrer"
                    className="text-sm text-indigo-600 hover:underline">View on site ↗</a>
            </div>

            {today ? (
                <div className="px-6 py-5 flex items-center gap-4">
                    {today.image ? (
                        <img src={today.image} alt={today.name}
                            className="h-16 w-16 rounded-lg object-cover bg-gray-100 shrink-0" />
                    ) : (
                        <div className="h-16 w-16 rounded-lg bg-gray-100 shrink-0" />
                    )}

                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-semibold text-gray-500">
                                #{today.number ?? '—'}
                            </span>
                            {today.is_preset && (
                                <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                    preset
                                </span>
                            )}
                            <span className="text-xs text-gray-400">{today.date}</span>
                        </div>
                        <p className="font-medium text-gray-900 truncate">
                            {today.product_id ? (
                                <Link href={route('admin.products.edit', today.product_id)}
                                    className="hover:text-indigo-600">{today.name}</Link>
                            ) : (
                                today.name
                            )}
                        </p>
                        <p className="text-sm text-gray-500 mt-0.5">
                            Answer <span className="font-semibold text-gray-700">{money(today.price)}</span>
                            <span className="mx-2 text-gray-300">·</span>
                            {today.plays} {today.plays === 1 ? 'play' : 'plays'}
                            <span className="mx-2 text-gray-300">·</span>
                            {today.wins} {today.wins === 1 ? 'win' : 'wins'}
                        </p>
                    </div>
                </div>
            ) : (
                <div className="px-6 py-5 text-sm text-gray-500">
                    No puzzle locked yet. Run{' '}
                    <code className="px-1 py-0.5 rounded bg-gray-100 text-gray-700">php artisan dropprice:lock</code>.
                </div>
            )}

            {upcoming && (
                <div className="px-6 py-3 border-t border-gray-100 bg-gray-50 text-sm text-gray-500 flex items-center gap-2">
                    <span className="font-medium text-gray-600">Up next</span>
                    <span className="text-gray-400">{upcoming.date}</span>
                    <span className="text-gray-300">·</span>
                    <span className="truncate">{upcoming.name}</span>
                    <span className="text-gray-300">·</span>
                    <span>{money(upcoming.price)}</span>
                    {upcoming.is_preset && (
                        <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                            queued
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
