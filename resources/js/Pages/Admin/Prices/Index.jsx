import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function StaleBadge({ days }) {
    if (days === null) {
        return <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">never checked</span>;
    }
    if (days > 14) {
        return <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">{days}d stale</span>;
    }
    if (days > 7) {
        return <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">{days}d</span>;
    }
    return <span className="text-[11px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">{days === 0 ? 'today' : `${days}d`}</span>;
}

function PriceRow({ product }) {
    const [price, setPrice] = useState(product.price ?? '');
    const [busy, setBusy] = useState(false);

    const changed = price !== '' && parseFloat(price) !== parseFloat(product.price ?? 0);

    function save() {
        setBusy(true);
        router.post(route('admin.prices.update', product.id), { price }, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    function confirm() {
        setBusy(true);
        router.post(route('admin.prices.confirm', product.id), {}, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    return (
        <tr className="hover:bg-gray-50">
            <td className="px-4 py-3">
                <p className="font-medium text-gray-900 max-w-[16rem] truncate">{product.name}</p>
                <p className="text-xs text-gray-400 font-mono">{product.asin ?? 'no ASIN'} · {product.snapshots} snapshot{product.snapshots === 1 ? '' : 's'}</p>
            </td>
            <td className="px-4 py-3"><StaleBadge days={product.days_stale} /></td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1.5">
                    <span className="text-gray-400">$</span>
                    <input type="number" step="0.01" min="0" value={price}
                        onChange={(e) => setPrice(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && price !== '' && save()}
                        className="w-24 rounded-lg border-gray-300 text-sm py-1.5" />
                </div>
            </td>
            <td className="px-4 py-3 text-right whitespace-nowrap space-x-2">
                {product.asin && (
                    <a href={`https://www.amazon.com/dp/${product.asin}`} target="_blank" rel="noreferrer noopener"
                        className="text-gray-400 hover:text-gray-600 text-xs underline">check&nbsp;↗</a>
                )}
                <button onClick={save} disabled={busy || price === '' || !changed}
                    className="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-indigo-700 disabled:opacity-40">
                    Save
                </button>
                <button onClick={confirm} disabled={busy}
                    className="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-gray-50 disabled:opacity-40">
                    Unchanged
                </button>
            </td>
        </tr>
    );
}

export default function PricesIndex({ products }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Price Check</h2>}>
            <Head title="Price Check" />

            <div className="py-8 px-4 max-w-5xl mx-auto space-y-4">
                {flash?.success && (
                    <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{flash.success}</div>
                )}

                <div className="bg-indigo-50 border border-indigo-100 text-indigo-900 px-4 py-3 rounded-lg text-sm">
                    Stalest first. Open <strong>check ↗</strong>, glance at the Amazon price, then either type the new
                    number and <strong>Save</strong> (records a snapshot) or hit <strong>Unchanged</strong> (refreshes the
                    "price checked" date). Five minutes here keeps every review's price widget honest.
                </div>

                <div className="bg-white rounded-xl shadow overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                            <tr>
                                <th className="px-4 py-3 text-left">Product</th>
                                <th className="px-4 py-3 text-left">Checked</th>
                                <th className="px-4 py-3 text-left">Price</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {products.data.map((p) => <PriceRow key={p.id} product={p} />)}
                        </tbody>
                    </table>
                    {products.data.length === 0 && (
                        <p className="px-4 py-8 text-center text-sm text-gray-400">No products attached to published posts yet.</p>
                    )}
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
