import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function PostsIndex({ posts }) {
    const { flash } = usePage().props;

    function destroy(id) {
        if (confirm('Delete this post?')) {
            router.delete(route('admin.posts.destroy', id));
        }
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Posts</h2>}>
            <Head title="Posts" />

            <div className="py-8 px-4 max-w-7xl mx-auto space-y-4">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                        {flash.success}
                    </div>
                )}

                <div className="flex justify-between items-center">
                    <p className="text-sm text-gray-500">{posts.total} posts</p>
                    <Link href={route('admin.posts.create')}
                        className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
                        + New Post
                    </Link>
                </div>

                <div className="bg-white rounded-xl shadow overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th className="px-6 py-3 text-left">Title</th>
                                <th className="px-6 py-3 text-left">Type</th>
                                <th className="px-6 py-3 text-left">Author</th>
                                <th className="px-6 py-3 text-left">Status</th>
                                <th className="px-6 py-3 text-left">Published</th>
                                <th className="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {posts.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3 font-medium text-gray-900 max-w-xs truncate">{p.title}</td>
                                    <td className="px-6 py-3"><TypeBadge type={p.type} /></td>
                                    <td className="px-6 py-3 text-gray-500">{p.author}</td>
                                    <td className="px-6 py-3"><StatusBadge status={p.status} /></td>
                                    <td className="px-6 py-3 text-gray-500">{p.published_at ?? '—'}</td>
                                    <td className="px-6 py-3 text-right space-x-3">
                                        <Link href={route('admin.posts.edit', p.id)}
                                            className="text-indigo-600 hover:underline">Edit</Link>
                                        <button onClick={() => destroy(p.id)}
                                            className="text-red-500 hover:underline">Delete</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="flex gap-1">
                    {posts.links.map((link, i) => (
                        <Link key={i} href={link.url ?? '#'}
                            className={`px-3 py-1.5 rounded text-sm border ${link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'} ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function TypeBadge({ type }) {
    const labels = { article: 'Article', tech_tip: 'Tech Tip' };
    const colors = { article: 'bg-blue-100 text-blue-700', tech_tip: 'bg-purple-100 text-purple-700' };
    return <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${colors[type] ?? 'bg-gray-100 text-gray-600'}`}>{labels[type] ?? type}</span>;
}

function StatusBadge({ status }) {
    const colors = { published: 'bg-green-100 text-green-700', draft: 'bg-gray-100 text-gray-600', scheduled: 'bg-yellow-100 text-yellow-700' };
    return <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${colors[status] ?? colors.draft}`}>{status}</span>;
}
