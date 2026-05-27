import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function ProductsIndex({ products }) {
    const { flash } = usePage().props;

    function destroy(id) {
        if (confirm('Delete this product?')) {
            router.delete(route('admin.products.destroy', id));
        }
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Products</h2>}>
            <Head title="Products" />

            <div className="py-8 px-4 max-w-7xl mx-auto space-y-4">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>
                )}

                <div className="flex justify-between items-center">
                    <p className="text-sm text-gray-500">{products.total} products</p>
                    <Link href={route('admin.products.create')}
                        className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
                        + Add Product
                    </Link>
                </div>

                <div className="bg-white rounded-xl shadow overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th className="px-6 py-3 text-left">Name</th>
                                <th className="px-6 py-3 text-left">ASIN</th>
                                <th className="px-6 py-3 text-left">Category</th>
                                <th className="px-6 py-3 text-left">Price</th>
                                <th className="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {products.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3 font-medium text-gray-900 max-w-xs truncate">{p.name}</td>
                                    <td className="px-6 py-3 text-gray-500 font-mono text-xs">{p.asin ?? '—'}</td>
                                    <td className="px-6 py-3 text-gray-500">{p.category ?? '—'}</td>
                                    <td className="px-6 py-3 text-gray-700">{p.price ? `$${p.price}` : '—'}</td>
                                    <td className="px-6 py-3 text-right space-x-3">
                                        <Link href={route('admin.products.edit', p.id)} className="text-indigo-600 hover:underline">Edit</Link>
                                        <button onClick={() => destroy(p.id)} className="text-red-500 hover:underline">Delete</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex gap-1">
                    {products.links.map((link, i) => (
                        <Link key={i} href={link.url ?? '#'}
                            className={`px-3 py-1.5 rounded text-sm border ${link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'} ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
