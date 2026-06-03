import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function News({ posts, meta }) {
    const { data = [], current_page, last_page } = posts;

    return (
        <PublicLayout>
            <Head title="Tech News — GadgetDrop" />

            {/* ── Page header with red gradient accent ── */}
            <div className="bg-white border-b border-gray-200">
                <div className="h-1 bg-gradient-to-r from-rose-500 via-red-500 to-rose-400" />
                <div className="max-w-6xl mx-auto px-4 py-10">
                    <div className="flex items-center gap-3 mb-2">
                        <span className="inline-flex items-center gap-1.5 bg-rose-600 text-white text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full">
                            <SignalIcon className="w-3 h-3" />
                            Live Coverage
                        </span>
                    </div>
                    <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">Tech News</h1>
                    <p className="text-gray-500 mt-1 text-sm">
                        The latest in tech — breaking stories, product launches, and industry moves.
                    </p>
                </div>
            </div>

            <div className="max-w-6xl mx-auto px-4 py-10">
                {data.length === 0 ? (
                    <div className="text-center py-24 text-gray-400">
                        <SignalIcon className="w-10 h-10 mx-auto mb-3 opacity-30" />
                        <p className="font-medium">No news yet — check back soon.</p>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            {data.map((post, i) => (
                                <NewsCard key={post.id} post={post} featured={i === 0 && current_page === 1} />
                            ))}
                        </div>

                        {/* Pagination */}
                        {last_page > 1 && (
                            <div className="mt-12 flex items-center justify-center gap-3">
                                {current_page > 1 && (
                                    <button
                                        onClick={() => router.visit(route('news') + `?page=${current_page - 1}`)}
                                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        <ChevronLeftIcon className="w-4 h-4" />
                                        Previous
                                    </button>
                                )}
                                <span className="text-sm text-gray-500">
                                    Page {current_page} of {last_page}
                                </span>
                                {current_page < last_page && (
                                    <button
                                        onClick={() => router.visit(route('news') + `?page=${current_page + 1}`)}
                                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                        Next
                                        <ChevronRightIcon className="w-4 h-4" />
                                    </button>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </PublicLayout>
    );
}

function NewsCard({ post, featured = false }) {
    const isBreaking = isWithin24h(post.published_at_iso);
    const sourceDomain = extractDomain(post.source_url);

    if (featured) {
        return (
            <Link href={route('posts.show', post.slug)}
                className="sm:col-span-2 lg:col-span-3 group block bg-white rounded-2xl border border-rose-100 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <div className="h-1.5 bg-gradient-to-r from-rose-500 via-red-500 to-rose-400" />
                <div className="flex flex-col md:flex-row">
                    {post.featured_image && (
                        <div className="md:w-2/5 shrink-0">
                            <img
                                src={post.featured_image}
                                alt={post.title}
                                className="w-full h-56 md:h-full object-cover"
                            />
                        </div>
                    )}
                    <div className="p-6 flex flex-col justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 mb-3 flex-wrap">
                                {isBreaking && <BreakingBadge />}
                                {sourceDomain && <SourceChip domain={sourceDomain} />}
                            </div>
                            <h2 className="text-xl font-bold text-gray-900 group-hover:text-rose-700 transition-colors leading-snug mb-2">
                                {post.title}
                            </h2>
                            {post.excerpt && (
                                <p className="text-sm text-gray-500 line-clamp-3">{post.excerpt}</p>
                            )}
                        </div>
                        <div className="flex items-center gap-3 text-xs text-gray-400">
                            <span>{formatDate(post.published_at)}</span>
                            <span>·</span>
                            <span>{post.read_minutes} min read</span>
                        </div>
                    </div>
                </div>
            </Link>
        );
    }

    return (
        <Link href={route('posts.show', post.slug)}
            className="group block bg-white rounded-xl border border-gray-200 hover:border-rose-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
            <div className="h-1 bg-gradient-to-r from-rose-400 to-red-400 opacity-0 group-hover:opacity-100 transition-opacity" />

            {post.featured_image && (
                <div className="h-44 overflow-hidden">
                    <img
                        src={post.featured_image}
                        alt={post.title}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                </div>
            )}

            <div className="p-4 space-y-3">
                <div className="flex items-center gap-1.5 flex-wrap">
                    {isBreaking && <BreakingBadge />}
                    {sourceDomain && <SourceChip domain={sourceDomain} />}
                </div>

                <h3 className="text-sm font-bold text-gray-900 group-hover:text-rose-700 transition-colors leading-snug line-clamp-3">
                    {post.title}
                </h3>

                {post.excerpt && (
                    <p className="text-xs text-gray-500 line-clamp-2">{post.excerpt}</p>
                )}

                <div className="flex items-center justify-between text-xs text-gray-400 pt-1 border-t border-gray-100">
                    <span>{formatDate(post.published_at)}</span>
                    <span>{post.read_minutes} min read</span>
                </div>
            </div>
        </Link>
    );
}

function BreakingBadge() {
    return (
        <span className="inline-flex items-center gap-1 bg-rose-600 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full">
            <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" />
            Breaking
        </span>
    );
}

function SourceChip({ domain }) {
    return (
        <span className="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full border border-gray-200">
            <GlobeIcon className="w-2.5 h-2.5" />
            {domain}
        </span>
    );
}

/* ── Helpers ── */

function isWithin24h(isoDate) {
    if (!isoDate) return false;
    const diff = Date.now() - new Date(isoDate).getTime();
    return diff < 24 * 60 * 60 * 1000;
}

function extractDomain(url) {
    if (!url) return null;
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return null;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/* ── Icons ── */

function SignalIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
        </svg>
    );
}

function GlobeIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
        </svg>
    );
}

function ChevronLeftIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
    );
}

function ChevronRightIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
    );
}
