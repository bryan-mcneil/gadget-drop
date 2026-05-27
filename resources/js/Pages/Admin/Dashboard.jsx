import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ stats, recentPosts }) {
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
                                    <td className="px-6 py-3 text-gray-500">{p.published_at ?? '—'}</td>
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
