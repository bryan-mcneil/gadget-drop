import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function CategoryPage({ category, posts, categories }) {
    return (
        <PublicLayout>
            <Head title={`${category.name} — GadgetDrop`}>
                <meta name="description" content={`Browse ${category.name} reviews, picks, and buying guides on GadgetDrop. ${posts.total} posts and counting.`} />
            </Head>

            <CategoryHero category={category} postCount={posts.total} />

            <div className="relative">
                <div className="h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent" />

                <div className="max-w-6xl mx-auto px-4 py-12 grid grid-cols-1 lg:grid-cols-4 gap-10">
                    <main className="lg:col-span-3">
                        {posts.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-24 text-center">
                                <div className="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center mb-4">
                                    <svg className="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round"
                                            d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z" />
                                    </svg>
                                </div>
                                <p className="text-lg font-bold text-gray-900">No drops yet</p>
                                <p className="text-sm text-gray-400 mt-1 mb-6">Check back soon — we're always adding new picks.</p>
                                <Link href={route('home')}
                                    className="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-700 font-semibold transition-colors">
                                    ← Back to Home
                                </Link>
                            </div>
                        ) : (
                            <>
                                {/* Section header */}
                                <div className="flex items-center justify-between mb-8">
                                    <div className="flex items-center gap-3">
                                        <span className="w-1 h-7 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500" />
                                        <div>
                                            <h2 className="text-2xl font-extrabold text-gray-900 leading-none">
                                                {category.name}
                                            </h2>
                                            <p className="text-xs text-gray-400 mt-0.5 tracking-wide">
                                                {posts.total} post{posts.total !== 1 ? 's' : ''} in this category
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    {posts.data.map((post) => (
                                        <PostCard key={post.id} post={post} />
                                    ))}
                                </div>

                                <Pagination links={posts.links} />
                            </>
                        )}
                    </main>

                    {/* Sidebar */}
                    <aside className="space-y-5">
                        <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                            <h3 className="flex items-center gap-2 font-bold text-gray-800 text-xs uppercase tracking-widest mb-4">
                                <span className="w-3 h-px bg-indigo-400" />
                                Browse Categories
                            </h3>
                            <ul className="space-y-0.5">
                                {categories.map((cat) => (
                                    <li key={cat.id}>
                                        <Link
                                            href={route('category', cat.slug)}
                                            className={`flex items-center justify-between group py-1.5 px-2 rounded-lg text-sm transition-colors ${
                                                cat.slug === category.slug
                                                    ? 'bg-indigo-50 text-indigo-700 font-semibold'
                                                    : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50'
                                            }`}
                                        >
                                            <span>{cat.name}</span>
                                            <svg
                                                className={`w-3.5 h-3.5 flex-shrink-0 transition-colors ${
                                                    cat.slug === category.slug
                                                        ? 'text-indigo-400'
                                                        : 'text-gray-300 group-hover:text-indigo-400'
                                                }`}
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}
                                            >
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Mini disclosure */}
                        <p className="text-xs text-gray-400 px-1">
                            #ad #commissionsearned — As an Amazon Associate we earn from qualifying purchases.
                        </p>
                    </aside>
                </div>
            </div>
        </PublicLayout>
    );
}

/* ── Category hero ──────────────────────────────────────────── */
function CategoryHero({ category, postCount }) {
    return (
        <section
            className="relative overflow-hidden min-h-[300px] md:min-h-[360px] flex items-center"
            style={{ background: 'linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)' }}
        >
            {/* Dot-grid texture */}
            <div
                className="absolute inset-0 pointer-events-none"
                style={{
                    backgroundImage: 'radial-gradient(circle, rgba(99,102,241,0.18) 1px, transparent 1px)',
                    backgroundSize: '28px 28px',
                }}
            />

            {/* Diagonal rule lines for extra depth */}
            <div
                className="absolute inset-0 pointer-events-none opacity-[0.04]"
                style={{
                    backgroundImage: 'repeating-linear-gradient(45deg, white 0px, white 1px, transparent 0px, transparent 50%)',
                    backgroundSize: '20px 20px',
                }}
            />

            {/* Indigo glow orb */}
            <div className="absolute right-0 top-1/2 -translate-y-1/2 w-80 h-80 rounded-full bg-indigo-600/10 blur-3xl pointer-events-none" />

            {/* Top + bottom accent lines */}
            <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent" />
            <div className="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent" />

            <div className="relative z-10 max-w-6xl mx-auto px-4 py-14 w-full">
                {/* Breadcrumb */}
                <nav className="flex items-center gap-1.5 text-xs text-gray-500 mb-5">
                    <Link href={route('home')} className="hover:text-indigo-400 transition-colors">
                        Home
                    </Link>
                    <svg className="w-3 h-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                    <span className="text-gray-300 font-medium">{category.name}</span>
                </nav>

                {/* Label */}
                <span className="inline-flex items-center gap-1.5 text-indigo-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                    <span className="w-4 h-px bg-indigo-400" />
                    Category
                </span>

                {/* Name */}
                <h1 className="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white leading-tight max-w-2xl">
                    {category.name}
                </h1>

                {/* Description */}
                {category.description && (
                    <p className="mt-4 text-gray-300 text-base md:text-lg leading-relaxed max-w-xl">
                        {category.description}
                    </p>
                )}

                {/* Post count badge */}
                {postCount > 0 && (
                    <div className="mt-6 inline-flex items-center gap-2 bg-white/8 backdrop-blur-sm border border-white/10 text-white text-xs font-semibold px-4 py-2 rounded-full">
                        <span className="w-1.5 h-1.5 rounded-full bg-indigo-400" />
                        {postCount} drop{postCount !== 1 ? 's' : ''}
                    </div>
                )}
            </div>
        </section>
    );
}

/* ── Post card ──────────────────────────────────────────────── */
function PostCard({ post }) {
    return (
        <Link
            href={route('posts.show', post.slug)}
            className="group bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300 flex flex-col"
        >
            {/* Image */}
            <div className="relative overflow-hidden bg-indigo-50">
                {post.featured_image ? (
                    <img
                        src={post.featured_image}
                        alt={post.title}
                        className="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                ) : (
                    <div className="w-full h-48 bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                        <span className="text-indigo-300 font-black text-5xl select-none">G</span>
                    </div>
                )}
                <div className="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-white to-transparent" />
            </div>

            {/* Body */}
            <div className="p-5 flex flex-col flex-1">
                <div className="flex items-center gap-2 mb-2">
                    <p className="text-xs text-gray-400 tracking-wide">{post.published_at}</p>
                    {post.type === 'tech_tip' && (
                        <span className="text-xs bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full">
                            Tech Tip
                        </span>
                    )}
                </div>
                <h3 className="font-bold text-gray-900 group-hover:text-indigo-600 transition-colors leading-snug text-base">
                    {post.title}
                </h3>
                {post.excerpt && (
                    <p className="mt-2 text-sm text-gray-500 line-clamp-2 flex-1">{post.excerpt}</p>
                )}
                <div className="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600
                                opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                    Read more
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </div>
        </Link>
    );
}

/* ── Pagination ─────────────────────────────────────────────── */
function Pagination({ links }) {
    if (links.length <= 3) return null;

    return (
        <div className="mt-10 flex items-center justify-center flex-wrap gap-1.5">
            {links.map((link, i) => (
                <Link
                    key={i}
                    href={link.url ?? '#'}
                    className={`min-w-[38px] h-9 flex items-center justify-center px-3 rounded-xl text-sm font-medium transition-colors ${
                        link.active
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : link.url
                                ? 'bg-white border border-gray-200 text-gray-700 hover:border-indigo-300 hover:text-indigo-600'
                                : 'bg-white border border-gray-100 text-gray-300 pointer-events-none select-none'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}
