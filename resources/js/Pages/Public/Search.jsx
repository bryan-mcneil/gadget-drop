import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function SearchPage({ query, posts, categories, tags }) {
    const { navigation } = usePage().props;
    const popularTags = navigation?.popularTags ?? [];
    const [input, setInput] = useState(query);
    const total = posts.length + categories.length + tags.length;

    function handleSubmit(e) {
        e.preventDefault();
        const q = input.trim();
        if (q) router.visit(route('search') + '?q=' + encodeURIComponent(q));
    }

    return (
        <PublicLayout>
            <Head title={query ? `"${query}" | Search` : 'Search | GadgetDrop'}>
                <meta name="robots" content="noindex, follow" />
                <meta name="description" content={query ? `Search results for "${query}" on GadgetDrop.` : 'Search GadgetDrop for tech reviews, gadget picks, and buying guides.'} />
            </Head>

            {/* ── Hero search bar ── */}
            <section
                className="relative overflow-hidden py-14 md:py-20"
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
                {/* Glow orb */}
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[300px] rounded-full bg-indigo-700/15 blur-[80px] pointer-events-none" />
                {/* Accent lines */}
                <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent" />

                <div className="relative max-w-2xl mx-auto px-4 text-center">
                    <span className="inline-flex items-center gap-1.5 text-indigo-400 text-xs font-bold uppercase tracking-[0.15em] mb-4">
                        <span className="w-4 h-px bg-indigo-400" />
                        Search
                        <span className="w-4 h-px bg-indigo-400" />
                    </span>
                    <h1 className="text-3xl md:text-4xl font-extrabold text-white mb-8 tracking-tight">
                        Find your next drop
                    </h1>

                    <form onSubmit={handleSubmit}>
                        <div className="flex gap-2 items-center bg-white/95 rounded-2xl p-2 shadow-xl shadow-black/30">
                            <svg className="w-5 h-5 text-gray-400 ml-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                            <input
                                type="search"
                                value={input}
                                onChange={e => setInput(e.target.value)}
                                placeholder="Search posts, categories, tags…"
                                autoFocus
                                className="flex-1 bg-transparent text-gray-900 placeholder-gray-400 text-base py-1.5 px-2 focus:outline-none border-0 ring-0"
                            />
                            <button
                                type="submit"
                                className="flex-shrink-0 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm px-5 py-2.5 rounded-xl transition-colors"
                            >
                                Search
                            </button>
                        </div>
                    </form>

                    {/* Result count */}
                    {query && total > 0 && (
                        <p className="mt-5 text-indigo-300/70 text-sm">
                            {total} result{total !== 1 ? 's' : ''} for{' '}
                            <span className="text-indigo-300 font-semibold">"{query}"</span>
                        </p>
                    )}
                </div>
            </section>

            {/* ── Results / suggestions ── */}
            <div className="h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent" />

            <div className="max-w-4xl mx-auto px-4 py-12 space-y-10">

                {/* No query — show popular tags as suggestions */}
                {!query && (
                    <div className="text-center space-y-8 py-6">
                        <p className="text-gray-400 text-sm">Start typing to search posts, categories, and tags.</p>
                        {popularTags.length > 0 && (
                            <div>
                                <p className="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Popular Tags</p>
                                <div className="flex flex-wrap justify-center gap-2">
                                    {popularTags.slice(0, 24).map(tag => (
                                        <Link
                                            key={tag.id}
                                            href={`${route('search')}?q=${encodeURIComponent(tag.name)}`}
                                            className="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-full font-medium transition-colors"
                                        >
                                            #{tag.name}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Query with no results */}
                {query && total === 0 && (
                    <div className="text-center py-16 space-y-4">
                        <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-50 mb-2">
                            <svg className="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round"
                                    d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                        </div>
                        <p className="text-lg font-bold text-gray-900">No results for "{query}"</p>
                        <p className="text-sm text-gray-400">Try a different keyword or browse a category from the nav.</p>
                        {popularTags.length > 0 && (
                            <div className="pt-4">
                                <p className="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Try these tags</p>
                                <div className="flex flex-wrap justify-center gap-2">
                                    {popularTags.slice(0, 12).map(tag => (
                                        <Link
                                            key={tag.id}
                                            href={`${route('search')}?q=${encodeURIComponent(tag.name)}`}
                                            className="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-full font-medium transition-colors"
                                        >
                                            #{tag.name}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Category results */}
                {categories.length > 0 && (
                    <section>
                        <SectionLabel>Categories</SectionLabel>
                        <div className="flex flex-wrap gap-2 mt-4">
                            {categories.map(cat => (
                                <Link
                                    key={cat.id}
                                    href={route('category', cat.slug)}
                                    className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-100 rounded-xl shadow-sm
                                               hover:border-indigo-200 hover:shadow-md text-sm font-semibold text-gray-800
                                               hover:text-indigo-700 transition-all group"
                                >
                                    {cat.name}
                                    <span className="text-xs font-medium text-gray-400 group-hover:text-indigo-400 transition-colors bg-gray-50 group-hover:bg-indigo-50 px-1.5 py-0.5 rounded-full">
                                        {cat.posts_count}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {/* Tag results */}
                {tags.length > 0 && (
                    <section>
                        <SectionLabel>Tags</SectionLabel>
                        <div className="flex flex-wrap gap-2 mt-4">
                            {tags.map(tag => (
                                <Link
                                    key={tag.id}
                                    href={`${route('search')}?q=${encodeURIComponent(tag.name)}`}
                                    className="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-full font-medium transition-colors"
                                >
                                    #{tag.name}
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {/* Post results */}
                {posts.length > 0 && (
                    <section>
                        <SectionLabel>{posts.length} Post{posts.length !== 1 ? 's' : ''}</SectionLabel>
                        <div className="mt-4 space-y-4">
                            {posts.map(post => (
                                <Link
                                    key={post.id}
                                    href={route('posts.show', post.slug)}
                                    className="group flex gap-4 items-start bg-white border border-gray-100 rounded-2xl p-4 shadow-sm hover:shadow-xl transition-shadow duration-300"
                                >
                                    {/* Thumbnail */}
                                    <div className="relative flex-shrink-0 w-24 h-24 rounded-xl overflow-hidden bg-indigo-50">
                                        {post.featured_image ? (
                                            <img
                                                src={post.featured_image}
                                                alt={post.title}
                                                loading="lazy"
                                                className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                            />
                                        ) : (
                                            <div className="w-full h-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                                                <span className="text-indigo-300 font-black text-2xl select-none">G</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Content */}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 mb-1">
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
                                            <p className="mt-1.5 text-sm text-gray-500 line-clamp-2">{post.excerpt}</p>
                                        )}
                                        <div className="mt-3 flex items-center gap-1 text-xs font-semibold text-indigo-600
                                                        opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            Read more
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </PublicLayout>
    );
}

function SectionLabel({ children }) {
    return (
        <div className="flex items-center gap-3">
            <span className="w-1 h-5 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500" />
            <h2 className="text-sm font-extrabold text-gray-900 uppercase tracking-wide">{children}</h2>
        </div>
    );
}
