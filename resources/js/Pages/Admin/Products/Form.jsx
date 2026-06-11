import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import ImageUploader from '@/Components/ImageUploader';

export default function ProductForm({ product, categories }) {
    const editing = !!product;

    const { data, setData, post, put, processing, errors } = useForm({
        name:                 product?.name ?? '',
        brand:                product?.brand ?? '',
        asin:                 product?.asin ?? '',
        gtin:                 product?.gtin ?? '',
        affiliate_url:        product?.affiliate_url ?? '',
        image_url:            product?.image_url ?? '',
        price:                product?.price ?? '',
        amazon_rating:        product?.amazon_rating ?? '',
        amazon_review_count:  product?.amazon_review_count ?? '',
        description:          product?.description ?? '',
        category_id:          product?.category_id ?? '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        editing ? put(route('admin.products.update', product.id)) : post(route('admin.products.store'));
    }

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center gap-3">
                <Link href={route('admin.products.index')} className="text-gray-400 hover:text-gray-600">← Products</Link>
                <h2 className="text-xl font-semibold text-gray-800">{editing ? 'Edit Product' : 'Add Product'}</h2>
            </div>
        }>
            <Head title={editing ? 'Edit Product' : 'Add Product'} />

            <form onSubmit={handleSubmit} className="py-8 px-4 max-w-2xl mx-auto space-y-5">
                {/* Core fields */}
                <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-5">
                    <Field label="Product Name *" error={errors.name}>
                        <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm" />
                    </Field>

                    <Field label="Brand" error={errors.brand}>
                        <input type="text" value={data.brand} onChange={(e) => setData('brand', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm" placeholder="e.g. Anker, Sony, Apple" />
                    </Field>

                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Amazon ASIN" error={errors.asin}>
                            <input type="text" value={data.asin} onChange={(e) => setData('asin', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm font-mono text-sm" placeholder="B0XXXXXXXX" />
                        </Field>
                        <Field label="Price ($)" error={errors.price}>
                            <input type="number" step="0.01" min="0" value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm" />
                        </Field>
                    </div>

                    <Field label="Affiliate URL *" error={errors.affiliate_url}>
                        <input type="url" value={data.affiliate_url} onChange={(e) => setData('affiliate_url', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm text-sm" placeholder="https://amzn.to/…" />
                    </Field>

                    <Field label="Product Image" error={errors.image_url}>
                        <ImageUploader
                            value={data.image_url}
                            onChange={(url) => setData('image_url', url)}
                            placeholder="Upload a PNG or paste a URL…"
                            previewClass="h-40"
                            previewFit="contain"
                        />
                    </Field>

                    <Field label="Category" error={errors.category_id}>
                        <select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm text-sm">
                            <option value="">None</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Description" error={errors.description}>
                        <textarea rows={4} value={data.description} onChange={(e) => setData('description', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm text-sm" />
                    </Field>
                </div>

                {/* Google Rich Results / SEO fields */}
                <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-5">
                    <h3 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Google Rich Results</h3>
                    <p className="text-xs text-gray-500 -mt-2">Used in Product JSON-LD for merchant listing and review snippets.</p>

                    <Field label="GTIN (UPC / EAN / barcode)" error={errors.gtin}>
                        <input type="text" value={data.gtin} onChange={(e) => setData('gtin', e.target.value)}
                            className="w-full border-gray-300 rounded-lg shadow-sm font-mono text-sm"
                            placeholder="12-digit UPC or 13-digit EAN" maxLength={14} />
                    </Field>

                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Amazon Rating (0–5)" error={errors.amazon_rating}>
                            <input type="number" step="0.1" min="0" max="5" value={data.amazon_rating}
                                onChange={(e) => setData('amazon_rating', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm"
                                placeholder="e.g. 4.5" />
                        </Field>
                        <Field label="Amazon Review Count" error={errors.amazon_review_count}>
                            <input type="number" step="1" min="0" value={data.amazon_review_count}
                                onChange={(e) => setData('amazon_review_count', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm"
                                placeholder="e.g. 1842" />
                        </Field>
                    </div>
                </div>

                <div className="flex gap-3">
                    <button type="submit" disabled={processing}
                        className="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                        {processing ? 'Saving…' : editing ? 'Update Product' : 'Add Product'}
                    </button>
                    <Link href={route('admin.products.index')}
                        className="bg-white border border-gray-300 text-gray-600 px-6 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </Link>
                </div>
            </form>
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
