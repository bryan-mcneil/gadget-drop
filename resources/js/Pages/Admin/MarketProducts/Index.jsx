import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function MarketProductsIndex({ products, categories, filters }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [category, setCategory] = useState(filters.category ?? '');

    function applyFilters(overrides = {}) {
        const params = { search, category, ...overrides };
        router.get(route('admin.market-products.index'),
            Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')),
            { preserveState: true, preserveScroll: true });
    }

    function submitSearch(e) {
        e.preventDefault();
        applyFilters();
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Market Products</h2>}>
            <Head title="Market Products" />

            <div className="py-8 px-4 max-w-7xl mx-auto space-y-4">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>
                )}

                <div className="flex flex-wrap justify-between items-center gap-3">
                    <p className="text-sm text-gray-500">
                        {products.total} tracked ASIN{products.total === 1 ? '' : 's'}
                        <span className="text-gray-400"> · imported by market:import — edit to add images &amp; tidy categories</span>
                    </p>

                    <form onSubmit={submitSearch} className="flex gap-2">
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search title, brand, or ASIN…"
                            className="w-64 border-gray-300 rounded-lg shadow-sm text-sm"
                        />
                        <select
                            value={category}
                            onChange={(e) => { setCategory(e.target.value); applyFilters({ category: e.target.value }); }}
                            className="border-gray-300 rounded-lg shadow-sm text-sm"
                        >
                            <option value="">All categories</option>
                            {categories.map((c) => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                        <button type="submit"
                            className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
                            Search
                        </button>
                    </form>
                </div>

                <div className="bg-white rounded-xl shadow overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th className="px-4 py-3 text-left w-14">Image</th>
                                <th className="px-4 py-3 text-left">Title</th>
                                <th className="px-4 py-3 text-left">ASIN</th>
                                <th className="px-4 py-3 text-left">Brand</th>
                                <th className="px-4 py-3 text-left">Category</th>
                                <th className="px-4 py-3 text-left">Price</th>
                                <th className="px-4 py-3 text-left">Last seen</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {products.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2">
                                        {p.image_url ? (
                                            <img src={p.image_url} alt="" loading="lazy"
                                                className="w-10 h-10 object-contain rounded bg-gray-50 border border-gray-100" />
                                        ) : (
                                            <div className="w-10 h-10 rounded bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center text-gray-300 text-xs">—</div>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 font-medium text-gray-900 max-w-sm truncate">{p.title}</td>
                                    <td className="px-4 py-2 font-mono text-xs whitespace-nowrap">
                                        {/* Admin-only link — public surfaces must use affiliate.redirect instead */}
                                        <a href={`https://www.amazon.com/dp/${p.asin}`} target="_blank"
                                            rel="nofollow noopener noreferrer"
                                            className="text-indigo-600 hover:underline" title="View on Amazon">
                                            {p.asin} ↗
                                        </a>
                                    </td>
                                    <td className="px-4 py-2 text-gray-500 max-w-[10rem] truncate">{p.brand ?? '-'}</td>
                                    <td className="px-4 py-2 text-gray-500 max-w-[10rem] truncate">{p.category ?? '-'}</td>
                                    <td className="px-4 py-2 text-gray-700 whitespace-nowrap">${p.current_price}</td>
                                    <td className="px-4 py-2 text-gray-500 whitespace-nowrap">{p.last_seen_at}</td>
                                    <td className="px-4 py-2 text-right">
                                        <Link href={route('admin.market-products.edit', p.id)} className="text-indigo-600 hover:underline">Edit</Link>
                                    </td>
                                </tr>
                            ))}
                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-gray-400">
                                        No market products match. Rows come from <code className="font-mono text-xs">php artisan market:import</code>.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex gap-1 flex-wrap">
                    {products.links.map((link, i) => (
                        <Link key={i} href={link.url ?? '#'} preserveScroll
                            className={`px-3 py-1.5 rounded text-sm border ${link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'} ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
