import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import ImageUploader from '@/Components/ImageUploader';

export default function PostForm({ post, categories, tags, products, authors }) {
    const editing = !!post;

    const { data, setData, post: submit, put, processing, errors } = useForm({
        type:            post?.type ?? 'article',
        title:           post?.title ?? '',
        slug:            post?.slug ?? '',
        excerpt:         post?.excerpt ?? '',
        body:            post?.body ?? '',
        source_url:      post?.source_url ?? '',
        featured_image:      post?.featured_image ?? '',
        featured_image_fit:  post?.featured_image_fit ?? 'cover',
        image_1:             post?.image_1 ?? '',
        image_1_fit:         post?.image_1_fit ?? 'cover',
        image_2:             post?.image_2 ?? '',
        image_2_fit:         post?.image_2_fit ?? 'cover',
        image_3:             post?.image_3 ?? '',
        image_3_fit:         post?.image_3_fit ?? 'cover',
        status:          post?.status ?? 'draft',
        published_at:    post?.published_at ?? new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16),
        user_id:         post?.user_id ?? '',
        category_ids:    post?.categories?.map((c) => c.id) ?? [],
        tag_ids:         post?.tags?.map((t) => t.id) ?? [],
        product_ids:     post?.products?.map((p) => p.id) ?? [],
        seo: {
            meta_title:       post?.seo_meta?.meta_title ?? '',
            meta_description: post?.seo_meta?.meta_description ?? '',
            focus_keyword:    post?.seo_meta?.focus_keyword ?? '',
        },
    });

    function slugify(str) {
        return str.toLowerCase().trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function handleTitleChange(value) {
        setData((prev) => ({
            ...prev,
            title: value,
            slug: editing ? prev.slug : slugify(value),
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        editing ? put(route('admin.posts.update', post.id)) : submit(route('admin.posts.store'));
    }

    function toggleId(field, id) {
        setData(field, data[field].includes(id)
            ? data[field].filter((x) => x !== id)
            : [...data[field], id]);
    }

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center gap-3">
                <Link href={route('admin.posts.index')} className="text-gray-400 hover:text-gray-600">← Posts</Link>
                <h2 className="text-xl font-semibold text-gray-800">{editing ? 'Edit Post' : 'New Post'}</h2>
            </div>
        }>
            <Head title={editing ? 'Edit Post' : 'New Post'} />

            <form onSubmit={handleSubmit} className="py-8 px-4 max-w-5xl mx-auto space-y-6">
                <div className="grid grid-cols-3 gap-6">
                    {/* Main content */}
                    <div className="col-span-2 space-y-5">
                        <Field label="Title" error={errors.title}>
                            <input type="text" value={data.title} onChange={(e) => handleTitleChange(e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm text-lg font-semibold" placeholder="Post title…" />
                        </Field>

                        <Field label="Slug" error={errors.slug}>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-gray-400 whitespace-nowrap">gadgetdrop.tech/posts/</span>
                                <input type="text" value={data.slug} onChange={(e) => setData('slug', slugify(e.target.value))}
                                    className="flex-1 border-gray-300 rounded-lg shadow-sm text-sm font-mono" placeholder="post-slug" />
                            </div>
                        </Field>

                        <Field label="Excerpt" error={errors.excerpt}>
                            <textarea rows={2} value={data.excerpt} onChange={(e) => setData('excerpt', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm" placeholder="Short summary shown in listings…" />
                        </Field>

                        <Field label="Body" error={errors.body}>
                            <textarea rows={18} value={data.body} onChange={(e) => setData('body', e.target.value)}
                                className="w-full border-gray-300 rounded-lg shadow-sm text-sm font-mono" placeholder="Write your post in Markdown…" />
                        </Field>

                        {/* SEO panel */}
                        <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4">
                            <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide">SEO</h3>
                            <Field label={`Meta Title (${data.seo.meta_title.length}/70)`} error={errors['seo.meta_title']}>
                                <input type="text" maxLength={70} value={data.seo.meta_title}
                                    onChange={(e) => setData('seo', { ...data.seo, meta_title: e.target.value })}
                                    className="w-full border-gray-300 rounded-lg shadow-sm text-sm" />
                            </Field>
                            <Field label={`Meta Description (${data.seo.meta_description.length}/320)`} error={errors['seo.meta_description']}>
                                <textarea rows={2} maxLength={320} value={data.seo.meta_description}
                                    onChange={(e) => setData('seo', { ...data.seo, meta_description: e.target.value })}
                                    className="w-full border-gray-300 rounded-lg shadow-sm text-sm" />
                            </Field>
                            <Field label="Focus Keyword" error={errors['seo.focus_keyword']}>
                                <input type="text" value={data.seo.focus_keyword}
                                    onChange={(e) => setData('seo', { ...data.seo, focus_keyword: e.target.value })}
                                    className="w-full border-gray-300 rounded-lg shadow-sm text-sm" placeholder="e.g. best wireless earbuds 2025" />
                            </Field>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-5">
                        {/* Author */}
                        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                            <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide">Author</h3>
                            <Field label="Written by" error={errors.user_id}>
                                <select value={data.user_id} onChange={(e) => setData('user_id', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg shadow-sm text-sm">
                                    <option value="">— Select author —</option>
                                    {authors.map((a) => (
                                        <option key={a.id} value={a.id}>{a.name}</option>
                                    ))}
                                </select>
                            </Field>
                            {data.user_id && (() => {
                                const author = authors.find((a) => a.id === parseInt(data.user_id));
                                return author?.bio ? (
                                    <p className="text-xs text-gray-400 italic">{author.bio}</p>
                                ) : null;
                            })()}
                        </div>

                        {/* Publish */}
                        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                            <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide">Publish</h3>
                            <Field label="Post Type" error={errors.type}>
                                <select value={data.type} onChange={(e) => setData('type', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg shadow-sm text-sm">
                                    <option value="article">Article</option>
                                    <option value="tech_tip">Tech Tip</option>
                                </select>
                            </Field>
                            {data.type === 'tech_tip' && (
                                <Field label="Reddit Source URL" error={errors.source_url}>
                                    <input type="url" value={data.source_url}
                                        onChange={(e) => setData('source_url', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm text-sm"
                                        placeholder="https://reddit.com/r/…" />
                                </Field>
                            )}
                            <Field label="Status" error={errors.status}>
                                <select value={data.status} onChange={(e) => setData('status', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg shadow-sm text-sm">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="scheduled">Scheduled</option>
                                </select>
                            </Field>
                            {data.status !== 'draft' && (
                                <Field label="Publish Date" error={errors.published_at}>
                                    <input type="datetime-local" value={data.published_at}
                                        onChange={(e) => setData('published_at', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm text-sm" />
                                </Field>
                            )}
                            <Field label="Featured Image" error={errors.featured_image}>
                                <ImageUploader
                                    value={data.featured_image}
                                    onChange={(url) => setData('featured_image', url)}
                                    previewClass="h-28"
                                />
                                {data.featured_image && (
                                    <FitToggle
                                        value={data.featured_image_fit}
                                        onChange={(v) => setData('featured_image_fit', v)}
                                    />
                                )}
                            </Field>
                            <button type="submit" disabled={processing}
                                className="w-full bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                                {processing ? 'Saving…' : editing ? 'Update Post' : 'Create Post'}
                            </button>
                        </div>

                        {/* Inline Images */}
                        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                            <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide">Inline Images</h3>
                            <p className="text-xs text-gray-400">These appear at ~⅓, ~⅔, and end of the post body.</p>
                            {[1, 2, 3].map((n) => (
                                <Field key={n} label={`Image ${n}`} error={errors[`image_${n}`]}>
                                    <ImageUploader
                                        value={data[`image_${n}`]}
                                        onChange={(url) => setData(`image_${n}`, url)}
                                        previewClass="h-24"
                                    />
                                    {data[`image_${n}`] && (
                                        <FitToggle
                                            value={data[`image_${n}_fit`]}
                                            onChange={(v) => setData(`image_${n}_fit`, v)}
                                        />
                                    )}
                                </Field>
                            ))}
                        </div>

                        {/* Categories */}
                        <CheckboxGroup label="Categories" items={categories} selected={data.category_ids}
                            onToggle={(id) => toggleId('category_ids', id)} />

                        {/* Tags */}
                        <CheckboxGroup label="Tags" items={tags} selected={data.tag_ids}
                            onToggle={(id) => toggleId('tag_ids', id)} />

                        {/* Products */}
                        <CheckboxGroup label="Products" items={products} selected={data.product_ids}
                            onToggle={(id) => toggleId('product_ids', id)}
                            renderLabel={(p) => `${p.name}${p.price ? ` — $${p.price}` : ''}`} />
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function FitToggle({ value, onChange }) {
    return (
        <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-xs text-gray-400">Display:</span>
            {[
                { val: 'cover',   label: 'Crop to fill' },
                { val: 'contain', label: 'Show full image' },
            ].map(({ val, label }) => (
                <button
                    key={val}
                    type="button"
                    onClick={() => onChange(val)}
                    className={`px-2.5 py-1 rounded-md text-xs font-medium transition-colors ${
                        value === val
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    }`}>
                    {label}
                </button>
            ))}
        </div>
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

function CheckboxGroup({ label, items, selected, onToggle, renderLabel }) {
    return (
        <div className="bg-white border border-gray-200 rounded-xl p-5">
            <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide mb-3">{label}</h3>
            <div className="space-y-2 max-h-48 overflow-y-auto">
                {items.length === 0 && <p className="text-xs text-gray-400">None yet.</p>}
                {items.map((item) => (
                    <label key={item.id} className="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" checked={selected.includes(item.id)} onChange={() => onToggle(item.id)}
                            className="rounded border-gray-300 text-indigo-600" />
                        {renderLabel ? renderLabel(item) : item.name}
                    </label>
                ))}
            </div>
        </div>
    );
}
