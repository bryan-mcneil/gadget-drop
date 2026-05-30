import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

export default function AuthorPage({ author, posts, totalViews, postCount }) {
    const articles = posts.filter(p => p.type !== 'tech_tip');
    const techTips = posts.filter(p => p.type === 'tech_tip');

    return (
        <PublicLayout>
            <Head title={`${author.name} | GadgetDrop`}>
                <meta name="description" content={author.bio ?? `Posts by ${author.name} on GadgetDrop.`} />
            </Head>

            {/* ── Hero ─────────────────────────────────────────── */}
            <div className="relative overflow-hidden bg-white border-b border-gray-100">
                {/* Blurred colour blobs */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0">
                    <div className="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-100/60 blur-3xl" />
                    <div className="absolute -bottom-16 -left-16 w-72 h-72 rounded-full bg-violet-100/50 blur-3xl" />
                </div>

                <div className="relative max-w-4xl mx-auto px-4 py-16 flex flex-col sm:flex-row items-center sm:items-start gap-8">
                    {/* Avatar */}
                    <div className="flex-shrink-0">
                        {author.avatar_url ? (
                            <img
                                src={author.avatar_url}
                                alt={author.name}
                                className="w-28 h-28 rounded-full object-cover ring-4 ring-white shadow-xl"
                            />
                        ) : (
                            <div className="w-28 h-28 rounded-full ring-4 ring-white shadow-xl bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center">
                                <span className="text-white font-extrabold text-5xl leading-none select-none">
                                    {author.name.charAt(0).toUpperCase()}
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Info */}
                    <div className="text-center sm:text-left min-w-0">
                        <p className="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-1">
                            GadgetDrop Writer
                        </p>
                        <h1 className="text-3xl font-extrabold text-gray-900 tracking-tight">
                            {author.name}
                        </h1>

                        {author.bio && (
                            <p className="mt-3 text-gray-500 max-w-lg leading-relaxed">
                                {author.bio}
                            </p>
                        )}

                        {/* Stats strip */}
                        <div className="mt-5 flex flex-wrap justify-center sm:justify-start gap-x-6 gap-y-2">
                            <StatPill label="posts" value={postCount} />
                            <StatPill label="total views" value={fmtNum(totalViews)} />
                            {author.since && <StatPill label="first post" value={author.since} />}
                        </div>

                        {/* Persona disclosure */}
                        <p className="mt-4 text-xs text-gray-400 max-w-sm text-center sm:text-left">
                            {author.name} is a GadgetDrop editorial persona — a distinct writing voice maintained by the GadgetDrop team.{' '}
                            <a href={route('about')} className="underline hover:text-gray-600">Learn more →</a>
                        </p>
                    </div>
                </div>
            </div>

            {/* ── Post sections ─────────────────────────────────── */}
            <div className="max-w-4xl mx-auto px-4 py-12 space-y-14">

                {articles.length > 0 && (
                    <section>
                        <SectionHeading label="Articles & Reviews" count={articles.length} />
                        <div className="grid sm:grid-cols-2 gap-6 mt-6">
                            {articles.map(p => <PostCard key={p.id} post={p} />)}
                        </div>
                    </section>
                )}

                {techTips.length > 0 && (
                    <section>
                        <SectionHeading label="Tech Tips" count={techTips.length} emerald />
                        <div className="grid sm:grid-cols-2 gap-6 mt-6">
                            {techTips.map(p => <PostCard key={p.id} post={p} emerald />)}
                        </div>
                    </section>
                )}

                {posts.length === 0 && (
                    <p className="text-center text-gray-400 py-24 text-sm">No published posts yet.</p>
                )}
            </div>
        </PublicLayout>
    );
}

/* ─── Stat pill ──────────────────────────────────────────────── */
function StatPill({ label, value }) {
    return (
        <div className="flex items-center gap-1.5 text-sm text-gray-500">
            <span className="font-semibold text-gray-800">{value}</span>
            <span>{label}</span>
        </div>
    );
}

/* ─── Section heading ────────────────────────────────────────── */
function SectionHeading({ label, count, emerald = false }) {
    return (
        <div className="flex items-center gap-3">
            <h2 className={`text-xs font-semibold uppercase tracking-widest ${emerald ? 'text-emerald-600' : 'text-indigo-500'}`}>
                {label}
            </h2>
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${emerald ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700'}`}>
                {count}
            </span>
            <span className="flex-1 h-px bg-gray-100" />
        </div>
    );
}

/* ─── Post card ──────────────────────────────────────────────── */
function PostCard({ post, emerald = false }) {
    return (
        <Link
            href={route('posts.show', post.slug)}
            className="group flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all duration-200"
        >
            {post.featured_image ? (
                <img src={post.featured_image} alt={post.title} className="w-full h-44 object-cover" />
            ) : (
                <div className={`w-full h-44 flex items-center justify-center ${emerald ? 'bg-emerald-50' : 'bg-indigo-50'}`}>
                    <span className={`font-extrabold text-6xl select-none ${emerald ? 'text-emerald-200' : 'text-indigo-200'}`}>
                        {post.title.charAt(0).toUpperCase()}
                    </span>
                </div>
            )}

            <div className="flex flex-col flex-1 p-5">
                {emerald && (
                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 mb-2">
                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Tech Tip
                    </span>
                )}
                <h3 className={`font-semibold text-gray-900 leading-snug transition-colors ${emerald ? 'group-hover:text-emerald-700' : 'group-hover:text-indigo-600'}`}>
                    {post.title}
                </h3>
                {post.excerpt && (
                    <p className="text-sm text-gray-500 mt-2 line-clamp-2 flex-1">{post.excerpt}</p>
                )}
                <div className="flex items-center justify-between mt-4">
                    <span className="text-xs text-gray-400">{post.published_at}</span>
                    {post.view_count > 0 && (
                        <span className="text-xs text-gray-400">{fmtNum(post.view_count)} views</span>
                    )}
                </div>
            </div>
        </Link>
    );
}

/* ─── Helpers ────────────────────────────────────────────────── */
function fmtNum(n) {
    if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(n);
}
