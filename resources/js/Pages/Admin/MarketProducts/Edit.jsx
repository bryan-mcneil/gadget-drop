import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import ImageUploader from '@/Components/ImageUploader';

export default function MarketProductEdit({ product, snapshots, categories }) {
    // Admin-only link — public surfaces must use affiliate.redirect instead.
    const amazonUrl = product.url ?? `https://www.amazon.com/dp/${product.asin}`;

    const { data, setData, put, processing, errors } = useForm({
        title:       product.title ?? '',
        description: product.description ?? '',
        brand:       product.brand ?? '',
        category:    product.category ?? '',
        image_url:   product.image_url ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        put(route('admin.market-products.update', product.id));
    }

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center gap-3">
                <Link href={route('admin.market-products.index')} className="text-gray-400 hover:text-gray-600">← Market Products</Link>
                <h2 className="text-xl font-semibold text-gray-800">Edit Market Product</h2>
                <a href={amazonUrl} target="_blank" rel="nofollow noopener noreferrer"
                    title="View on Amazon"
                    className="text-xs font-mono bg-gray-100 text-gray-500 px-2 py-1 rounded hover:bg-gray-200 hover:text-gray-700">
                    {product.asin} ↗
                </a>
            </div>
        }>
            <Head title="Edit Market Product" />

            <div className="py-8 px-4 max-w-4xl mx-auto grid gap-5 md:grid-cols-[1fr_18rem] items-start">
                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-5">
                        <Field label="Title *" error={errors.title}>
                            <input type="text" value={data.title} onChange={(e) => setData('title', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm" maxLength={500} />
                        </Field>

                        <Field label="Description" error={errors.description}>
                            <textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm" maxLength={500} />
                        </Field>

                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Brand" error={errors.brand}>
                                <input type="text" value={data.brand} onChange={(e) => setData('brand', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg shadow-sm" placeholder="e.g. Anker, Sony" />
                            </Field>
                            <Field label="Category" error={errors.category}>
                                <input type="text" list="market-categories" value={data.category}
                                    onChange={(e) => setData('category', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg shadow-sm" placeholder="e.g. Chargers" />
                                <datalist id="market-categories">
                                    {categories.map((c) => <option key={c} value={c} />)}
                                </datalist>
                            </Field>
                        </div>

                        <Field label="Product Image" error={errors.image_url}>
                            <ImageUploader
                                value={data.image_url}
                                onChange={(url) => setData('image_url', url)}
                                placeholder="Upload an image or paste a URL…"
                                previewClass="h-40"
                                previewFit="contain"
                            />
                        </Field>

                        <p className="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                            Heads up: <strong>market:import refreshes this row</strong> whenever its ASIN shows up in a
                            file — title is always overwritten; description, brand, and category are overwritten when the
                            file carries a value for them. The image is yours alone; imports never touch it.
                        </p>
                    </div>

                    <div className="flex gap-3">
                        <button type="submit" disabled={processing}
                            className="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                            {processing ? 'Saving…' : 'Update Product'}
                        </button>
                        <Link href={route('admin.market-products.index')}
                            className="bg-white border border-gray-300 text-gray-600 px-6 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                            Cancel
                        </Link>
                    </div>
                </form>

                {/* Import-owned data — read-only */}
                <div className="space-y-5">
                    <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                        <h3 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Import Data</h3>
                        <Stat label="Current price" value={`$${product.current_price}`} />
                        {product.list_price && <Stat label="List price" value={`$${product.list_price}`} />}
                        {product.rating && <Stat label="Rating" value={`${product.rating} ★${product.review_count ? ` (${product.review_count.toLocaleString()} reviews)` : ''}`} />}
                        <Stat label="First seen" value={product.first_seen_at} />
                        <Stat label="Last seen" value={product.last_seen_at} />
                        <div className="pt-1">
                            <a href={amazonUrl} target="_blank" rel="nofollow noopener noreferrer"
                                className="text-xs text-indigo-600 hover:underline break-all">
                                View on Amazon ↗
                            </a>
                        </div>
                    </div>

                    <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                        <h3 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Price History</h3>
                        {snapshots.length === 0 ? (
                            <p className="text-xs text-gray-400">No snapshots yet.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {snapshots.map((s, i) => (
                                    <li key={i} className="flex justify-between py-1.5 text-sm">
                                        <span className="text-gray-500">{s.date}</span>
                                        <span className="text-gray-800 font-medium">${s.price}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="text-[11px] text-gray-400">Change-only snapshots — same-price sightings advance “Last seen” instead.</p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div className="space-y-1">
            <label className="block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="flex justify-between text-sm">
            <span className="text-gray-500">{label}</span>
            <span className="text-gray-800 font-medium text-right">{value}</span>
        </div>
    );
}
