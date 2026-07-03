import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import ImageUploader from '@/Components/ImageUploader';

function toDatetimeLocal(val) {
    if (!val) return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    if (val.includes('T')) return val.slice(0, 16);
    if (val.length === 10) return val + 'T00:00';
    return val.slice(0, 16);
}

export default function PostForm({ post, categories, tags, products }) {
    const editing = !!post;

    const { data, setData, post: submit, put, processing, errors } = useForm({
        type: post?.type ?? 'article',
        title: post?.title ?? '',
        slug: post?.slug ?? '',
        excerpt: post?.excerpt ?? '',
        body: post?.body ?? '',
        source_url: post?.source_url ?? '',
        featured_image: post?.featured_image ?? '',
        featured_image_fit: post?.featured_image_fit ?? 'cover',
        featured_image_position: post?.featured_image_position ?? 'center center',
        hero_image: post?.hero_image ?? '',
        hero_image_position: post?.hero_image_position ?? 'center center',
        image_1: post?.image_1 ?? '',
        image_1_fit: post?.image_1_fit ?? 'cover',
        image_2: post?.image_2 ?? '',
        image_2_fit: post?.image_2_fit ?? 'cover',
        image_3: post?.image_3 ?? '',
        image_3_fit: post?.image_3_fit ?? 'cover',
        status: post?.status ?? 'draft',
        published_at: toDatetimeLocal(post?.published_at),
        // Single-author site: user_id is defaulted server-side (User::siteAuthor).
        user_id: post?.user_id ?? '',
        category_ids: post?.categories?.map((c) => c.id) ?? [],
        tag_ids: post?.tags?.map((t) => t.id) ?? [],
        product_ids: post?.products?.map((p) => p.id) ?? [],
        rating: post?.rating ?? null,
        pros: post?.pros ?? [],
        cons: post?.cons ?? [],
        seo: {
            meta_title: post?.seo_meta?.meta_title ?? '',
            meta_description: post?.seo_meta?.meta_description ?? '',
            focus_keyword: post?.seo_meta?.focus_keyword ?? '',
            noindex: post?.seo_meta?.noindex ?? false,
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
                    {/* ── Main content ── */}
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
                            <label className="flex items-start gap-2.5 cursor-pointer select-none">
                                <input type="checkbox" checked={!!data.seo.noindex}
                                    onChange={(e) => setData('seo', { ...data.seo, noindex: e.target.checked })}
                                    className="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span className="text-sm text-gray-600">
                                    <span className="font-medium text-gray-800">Noindex this post</span> — stays live for
                                    readers but is removed from Google's index and the sitemap (for thin/legacy posts
                                    not worth deleting).
                                </span>
                            </label>
                        </div>
                    </div>

                    {/* ── Sidebar ── */}
                    <div className="space-y-5">
                        {/* Publish */}
                        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                            <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide">Publish</h3>
                            <Field label="Post Type" error={errors.type}>
                                <select value={data.type} onChange={(e) => setData('type', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg shadow-sm text-sm">
                                    <option value="article">Article</option>
                                    <option value="tech_tip">Tech Tip</option>
                                    <option value="tech_news">Tech News</option>
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
                            {data.type === 'tech_news' && (
                                <Field label="Source URL" error={errors.source_url}>
                                    <input type="url" value={data.source_url}
                                        onChange={(e) => setData('source_url', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm text-sm"
                                        placeholder="https://techcrunch.com/…" />
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
                                    <>
                                        <FitToggle
                                            value={data.featured_image_fit}
                                            onChange={(v) => setData('featured_image_fit', v)}
                                        />
                                        <FocalPointPicker
                                            image={data.featured_image}
                                            value={data.featured_image_position}
                                            onChange={(v) => setData('featured_image_position', v)}
                                        />
                                    </>
                                )}
                            </Field>

                            <Field label="Hero / Banner Image" error={errors.hero_image}>
                                <p className="text-xs text-gray-400 mb-1.5">
                                    Optional wide crop for hero sections (carousel, breaking news). Falls back to featured image if not set.
                                    <br />
                                    <span className="text-gray-300">Recommended: 16:5 ratio, e.g. 1920×600</span>
                                </p>
                                <ImageUploader
                                    value={data.hero_image}
                                    onChange={(url) => setData('hero_image', url)}
                                    previewClass="h-20"
                                />
                                {data.hero_image && (
                                    <FocalPointPicker
                                        image={data.hero_image}
                                        value={data.hero_image_position}
                                        onChange={(v) => setData('hero_image_position', v)}
                                    />
                                )}
                                {data.hero_image && (
                                    <button
                                        type="button"
                                        onClick={() => { setData('hero_image', ''); setData('hero_image_position', 'center center'); }}
                                        className="mt-1.5 text-xs text-red-400 hover:text-red-600 transition-colors">
                                        Remove hero image
                                    </button>
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
                            renderLabel={(p) => `${p.name}${p.price ? ` · $${p.price}` : ''}`} />

                        {/* Review — articles only */}
                        {data.type === 'article' && (
                            <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                                <div>
                                    <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide">Review</h3>
                                    <p className="text-xs text-gray-400 mt-0.5">Powers Google rich results (star ratings, pros/cons).</p>
                                </div>

                                <Field label="Editorial Rating" error={errors.rating}>
                                    <StarPicker
                                        value={data.rating}
                                        onChange={(v) => setData('rating', v)}
                                    />
                                </Field>

                                <Field label="Pros" error={errors.pros}>
                                    <ListEditor
                                        items={data.pros}
                                        onChange={(items) => setData('pros', items)}
                                        placeholder="Add a pro…"
                                        color="emerald"
                                    />
                                </Field>

                                <Field label="Cons" error={errors.cons}>
                                    <ListEditor
                                        items={data.cons}
                                        onChange={(items) => setData('cons', items)}
                                        placeholder="Add a con…"
                                        color="rose"
                                    />
                                </Field>
                            </div>
                        )}
                    </div>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

/* ─── Star picker ────────────────────────────────────────────── */
const RATING_STEPS = [1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5];

function StarPicker({ value, onChange }) {
    const numVal = value ? Number(value) : null;

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-1">
                {RATING_STEPS.map((v) => (
                    <button
                        key={v}
                        type="button"
                        onClick={() => onChange(numVal === v ? null : v)}
                        className={`px-2.5 py-1 rounded-md text-xs font-semibold transition-colors ${numVal === v
                                ? 'bg-amber-400 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-amber-100 hover:text-amber-700'
                            }`}>
                        {v}
                    </button>
                ))}
            </div>
            {numVal ? (
                <p className="text-xs text-amber-600 font-medium">
                    {'★'.repeat(Math.floor(numVal))}{numVal % 1 ? '½' : ''} &nbsp;{numVal} / 5
                </p>
            ) : (
                <p className="text-xs text-gray-400">No rating set</p>
            )}
        </div>
    );
}

/* ─── Dynamic list editor ────────────────────────────────────── */
function ListEditor({ items, onChange, placeholder, color = 'gray' }) {
    const [draft, setDraft] = useState('');

    const dotColor = color === 'emerald' ? 'bg-emerald-400' : color === 'rose' ? 'bg-rose-400' : 'bg-gray-400';
    const addBtnCls = color === 'emerald'
        ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
        : color === 'rose'
            ? 'bg-rose-50 text-rose-700 hover:bg-rose-100'
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200';

    function add() {
        const trimmed = draft.trim();
        if (!trimmed) return;
        onChange([...items, trimmed]);
        setDraft('');
    }

    function remove(i) {
        onChange(items.filter((_, idx) => idx !== i));
    }

    function handleKey(e) {
        if (e.key === 'Enter') { e.preventDefault(); add(); }
    }

    return (
        <div className="space-y-1.5">
            {items.map((item, i) => (
                <div key={i} className="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-1.5">
                    <span className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${dotColor}`} />
                    <span className="flex-1 text-sm text-gray-700">{item}</span>
                    <button type="button" onClick={() => remove(i)}
                        className="text-gray-300 hover:text-red-400 transition-colors text-sm leading-none">✕</button>
                </div>
            ))}
            <div className="flex gap-2 pt-0.5">
                <input
                    type="text"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={handleKey}
                    placeholder={placeholder}
                    className="flex-1 border-gray-300 rounded-lg shadow-sm text-sm"
                />
                <button type="button" onClick={add}
                    className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${addBtnCls}`}>
                    Add
                </button>
            </div>
        </div>
    );
}

/* ─── Shared helpers ─────────────────────────────────────────── */
function FitToggle({ value, onChange }) {
    return (
        <div className="flex items-center gap-1.5 mt-1.5">
            <span className="text-xs text-gray-400">Display:</span>
            {[
                { val: 'cover', label: 'Crop to fill' },
                { val: 'contain', label: 'Show full image' },
            ].map(({ val, label }) => (
                <button
                    key={val}
                    type="button"
                    onClick={() => onChange(val)}
                    className={`px-2.5 py-1 rounded-md text-xs font-medium transition-colors ${value === val
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}>
                    {label}
                </button>
            ))}
        </div>
    );
}

const FOCAL_POINTS = [
    { label: 'Top left', value: 'left top' },
    { label: 'Top center', value: 'center top' },
    { label: 'Top right', value: 'right top' },
    { label: 'Left', value: 'left center' },
    { label: 'Center', value: 'center center' },
    { label: 'Right', value: 'right center' },
    { label: 'Bottom left', value: 'bottom left' },
    { label: 'Bottom center', value: 'center bottom' },
    { label: 'Bottom right', value: 'right bottom' },
];

function FocalPointPicker({ image, value, onChange }) {
    return (
        <div className="mt-3 space-y-2">
            <p className="text-xs text-gray-400">Focal point <span className="text-gray-300">: controls which part stays visible when cropped</span></p>
            <div className="flex gap-3 items-start">
                {/* 3×3 picker grid */}
                <div
                    className="relative flex-shrink-0 rounded-lg overflow-hidden border border-gray-200"
                    style={{ width: 96, height: 64 }}
                >
                    <img
                        src={image}
                        alt=""
                        className="absolute inset-0 w-full h-full object-cover"
                        style={{ objectPosition: value }}
                    />
                    {/* 3×3 click grid overlaid on the preview */}
                    <div className="absolute inset-0 grid grid-cols-3 grid-rows-3">
                        {FOCAL_POINTS.map((fp) => (
                            <button
                                key={fp.value}
                                type="button"
                                title={fp.label}
                                onClick={() => onChange(fp.value)}
                                className={`transition-all ${value === fp.value
                                        ? 'bg-indigo-500/60'
                                        : 'bg-transparent hover:bg-white/30'
                                    }`}
                            >
                                {value === fp.value && (
                                    <span className="flex items-center justify-center w-full h-full">
                                        <span className="w-2 h-2 rounded-full bg-white shadow-md ring-1 ring-indigo-400" />
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Larger live preview showing how it looks cropped */}
                <div
                    className="relative flex-1 rounded-lg overflow-hidden border border-gray-200 bg-gray-100"
                    style={{ height: 64 }}
                >
                    <img
                        src={image}
                        alt=""
                        className="w-full h-full object-cover"
                        style={{ objectPosition: value }}
                    />
                    <span className="absolute bottom-1 right-1.5 text-[9px] text-white/60 font-mono bg-black/30 px-1 rounded">
                        {value}
                    </span>
                </div>
            </div>
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
    const [query, setQuery] = useState('');

    const getLabel = (item) => renderLabel ? renderLabel(item) : item.name;

    // Selected items always visible; unselected items filtered by query
    const visible = query.trim()
        ? items.filter(item =>
            selected.includes(item.id) ||
            getLabel(item).toLowerCase().includes(query.toLowerCase())
        )
        : items;

    const hiddenSelectedCount = query.trim()
        ? 0
        : 0; // selected are always shown, so no hidden ones

    return (
        <div className="bg-white border border-gray-200 rounded-xl p-5">
            {/* Header */}
            <div className="flex items-center justify-between mb-3">
                <h3 className="font-semibold text-gray-700 text-sm uppercase tracking-wide">{label}</h3>
                {selected.length > 0 && (
                    <span className="text-xs font-semibold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                        {selected.length} selected
                    </span>
                )}
            </div>

            {/* Search input */}
            <div className="relative mb-2">
                <svg className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                </svg>
                <input
                    type="text"
                    value={query}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={`Search ${label.toLowerCase()}…`}
                    className="w-full pl-8 pr-7 py-1.5 text-xs border border-gray-200 rounded-lg bg-gray-50
                               focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400"
                />
                {query && (
                    <button
                        type="button"
                        onClick={() => setQuery('')}
                        className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 leading-none">
                        ✕
                    </button>
                )}
            </div>

            {/* List */}
            <div className="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                {items.length === 0 && (
                    <p className="text-xs text-gray-400 py-1">None yet.</p>
                )}
                {items.length > 0 && visible.length === 0 && (
                    <p className="text-xs text-gray-400 py-1">No results for "{query}"</p>
                )}
                {visible.map((item) => (
                    <label key={item.id}
                        className={`flex items-center gap-2 text-sm cursor-pointer px-2 py-1 rounded-lg transition-colors
                            ${selected.includes(item.id) ? 'bg-indigo-50' : 'hover:bg-gray-50'}`}>
                        <input
                            type="checkbox"
                            checked={selected.includes(item.id)}
                            onChange={() => onToggle(item.id)}
                            className="rounded border-gray-300 text-indigo-600 flex-shrink-0"
                        />
                        <span className={selected.includes(item.id) ? 'text-indigo-700 font-medium' : 'text-gray-700'}>
                            {getLabel(item)}
                        </span>
                    </label>
                ))}
            </div>

            {/* Footer hint when query is hiding unselected items */}
            {query && items.length > visible.length && (
                <p className="text-[10px] text-gray-400 mt-2">
                    {items.length - visible.length} item{items.length - visible.length > 1 ? 's' : ''} hidden by search
                </p>
            )}
        </div>
    );
}
