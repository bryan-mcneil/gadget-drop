import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import ReactMarkdown from 'react-markdown';
import PublicLayout from '@/Layouts/PublicLayout';
import AffiliateDisclosure from '@/Components/AffiliateDisclosure';
import ShareBar from '@/Components/ShareBar';
import AdUnit from '@/Components/AdUnit';
import PostJsonLd from '@/Components/PostJsonLd';
import AdaptiveImage from '@/Components/AdaptiveImage';

/* ── Custom ReactMarkdown renderers ─────────────────────────── */
const proseComponents = {
    hr: () => (
        <div className="my-10 h-0.5 rounded-full bg-gradient-to-r from-indigo-500 via-purple-400 to-indigo-200 opacity-60" />
    ),
    blockquote: ({ children }) => (
        <blockquote className="not-prose my-6 border-l-4 border-indigo-400 bg-indigo-50/70 px-5 py-4 rounded-r-xl italic text-gray-700 leading-relaxed text-base">
            {children}
        </blockquote>
    ),
};

export default function PostPage({ post, categoryPosts, tagPosts, recentPosts, relatedProducts = [] }) {
    const seo = post.seo_meta;

    return (
        <>
        <ReadingProgress />
        <PublicLayout>
            <Head>
                <title>{seo?.meta_title ?? `${post.title} | GadgetDrop`}</title>
                <meta name="description" content={seo?.meta_description ?? post.excerpt ?? `${post.title} — GadgetDrop`} />
                {seo?.canonical_url && <link rel="canonical" href={seo.canonical_url} />}
                <meta property="og:title" content={seo?.meta_title ?? post.title} />
                {seo?.og_image && <meta property="og:image" content={seo.og_image} />}
                <PostJsonLd post={post} />
            </Head>

            <div className="max-w-6xl mx-auto px-4 py-12 grid grid-cols-1 lg:grid-cols-4 gap-10">
                <article className="lg:col-span-3">
                    {/* News accent bar */}
                    {post.type === 'tech_news' && (
                        <div className="h-1 bg-gradient-to-r from-rose-500 via-red-500 to-rose-400 rounded-full mb-6 -mx-1" />
                    )}

                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex flex-wrap items-center gap-2 mb-3">
                            {post.type === 'tech_tip' && (
                                <span className="inline-flex items-center gap-1 text-xs bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1 rounded-full">
                                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Tech Tip
                                </span>
                            )}
                            {post.type === 'tech_news' && (
                                <>
                                    {isBreakingNews(post.published_at_iso) ? (
                                        <span className="inline-flex items-center gap-1.5 text-xs bg-rose-600 text-white font-bold px-2.5 py-1 rounded-full">
                                            <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" />
                                            Breaking
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-xs bg-rose-100 text-rose-700 font-bold px-2.5 py-1 rounded-full">
                                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                                            </svg>
                                            Tech News
                                        </span>
                                    )}
                                </>
                            )}
                            {post.categories.map((c) => (
                                <Link key={c.id} href={route('category', c.slug)}
                                    className="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-medium">{c.name}</Link>
                            ))}
                        </div>
                        <h1 className="text-3xl font-extrabold text-gray-900 leading-tight">{post.title}</h1>
                        <div className="mt-2 space-y-1">
                            <p className="text-sm text-gray-500">
                                By {post.user?.name} · {post.published_at}
                                {post.read_minutes && (
                                    <span className="ml-2 text-gray-400">· {post.read_minutes} min read</span>
                                )}
                            </p>
                            <ShareBar
                                url={route('posts.show', post.slug)}
                                shortUrl={post.short_url}
                                title={post.title}
                            />
                            {post.type !== 'tech_news' && <AffiliateDisclosure />}
                        </div>
                    </div>

                    {post.featured_image && (
                        <AdaptiveImage
                            src={post.featured_image}
                            alt={`Featured image for ${post.title}`}
                            fit={post.featured_image_fit ?? 'cover'}
                            className="w-full rounded-xl max-h-96"
                            wrapperClass="mb-8"
                            loading="eager"
                        />
                    )}

                    {/* Products */}
                    {post.products.length > 0 && (
                        <div className="mb-8 space-y-4">
                            {post.products.map((product) => (
                                <ProductCard key={product.id} product={product} postId={post.id} />
                            ))}
                        </div>
                    )}

                    {/* Body — split into thirds with inline images between sections */}
                    <BodyWithImages
                        body={post.body}
                        images={[post.image_1, post.image_2, post.image_3]}
                        fits={[post.image_1_fit, post.image_2_fit, post.image_3_fit]}
                    />

                    {/* Repeat CTA after body — captures readers who finished the article */}
                    {post.products.length > 0 && (
                        <div className="mt-10 pt-8 border-t border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                                Check Current Prices
                            </h2>
                            <div className="space-y-3">
                                {post.products.map((product) => (
                                    <div key={product.id} className="flex items-center justify-between gap-4 bg-gray-50 rounded-xl px-5 py-3">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-gray-900 text-sm truncate">{product.name}</p>
                                            {product.price && <p className="text-sm text-gray-500">${product.price}</p>}
                                        </div>
                                        <a href={route('affiliate.redirect', { product: product.id, post: post.id })}
                                            target="_blank" rel="nofollow sponsored"
                                            className="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2">
                                            View on Amazon →
                                        </a>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Tags */}
                    {post.tags.length > 0 && (
                        <div className="mt-8 flex flex-wrap gap-2">
                            {post.tags.map((tag) => (
                                <span key={tag.id} className="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full font-medium">#{tag.name}</span>
                            ))}
                        </div>
                    )}

                    {/* Source attribution for Tech News */}
                    {post.type === 'tech_news' && post.source_url && (
                        <div className="mt-6 pt-5 border-t border-rose-100 flex items-center gap-2.5">
                            <svg className="w-4 h-4 text-rose-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                            <p className="text-xs text-gray-400">
                                Source:{' '}
                                <a href={post.source_url} target="_blank" rel="nofollow noopener"
                                    className="underline hover:text-rose-600 transition-colors">
                                    {extractSourceDomain(post.source_url)}
                                </a>
                            </p>
                        </div>
                    )}

                    {/* Reddit attribution for Tech Tips */}
                    {post.type === 'tech_tip' && post.source_url && (
                        <div className="mt-6 pt-5 border-t border-gray-100 flex items-center gap-2.5">
                            <svg className="w-4 h-4 text-orange-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                            </svg>
                            <p className="text-xs text-gray-400">
                                Summarized from a{' '}
                                <a href={post.source_url} target="_blank" rel="nofollow noopener"
                                    className="underline hover:text-gray-600 transition-colors">
                                    Reddit community discussion
                                </a>
                                {' '}— edited for clarity and accuracy.
                            </p>
                        </div>
                    )}

                    {/* Author card */}
                    {post.user && (
                        <div className="mt-8 flex items-start gap-4 bg-white border border-gray-200 rounded-xl p-5">
                            <Link href={post.user.slug ? route('author', post.user.slug) : '#'} className="flex-shrink-0">
                                {post.user.avatar_url ? (
                                    <img src={post.user.avatar_url} alt={post.user.name} loading="lazy"
                                        className="w-14 h-14 rounded-full object-cover ring-2 ring-indigo-100 hover:ring-indigo-300 transition" />
                                ) : (
                                    <div className="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500
                                                    flex items-center justify-center ring-2 ring-indigo-100 hover:ring-indigo-300 transition">
                                        <span className="text-white font-bold text-xl">
                                            {post.user.name.charAt(0)}
                                        </span>
                                    </div>
                                )}
                            </Link>
                            <div>
                                <p className="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Written by</p>
                                <Link href={post.user.slug ? route('author', post.user.slug) : '#'}
                                    className="font-semibold text-gray-900 hover:text-indigo-600 transition-colors">
                                    {post.user.name}
                                </Link>
                                {post.user.bio && (
                                    <p className="text-sm text-gray-500 mt-1">{post.user.bio}</p>
                                )}
                                <Link href={post.user.slug ? route('author', post.user.slug) : '#'}
                                    className="inline-flex items-center gap-1 mt-2 text-xs text-indigo-500
                                               hover:text-indigo-700 font-medium transition-colors">
                                    View all posts →
                                </Link>
                            </div>
                        </div>
                    )}
                </article>

                {/* Sidebar */}
                <aside className="lg:border-l lg:border-gray-100 lg:pl-6 space-y-8">
                    {/* Related products for tips and news */}
                    {relatedProducts.length > 0 && (
                        <RelatedProductsSection products={relatedProducts} postId={post.id} />
                    )}

                    {post.type === 'tech_news' ? (
                        <>
                            <SidebarSection
                                title="More News"
                                posts={recentPosts}
                                emptyLabel="No other news yet."
                                rose
                            />
                            <SidebarSection
                                title="Related Stories"
                                posts={categoryPosts}
                                emptyLabel={null}
                                rose
                            />
                        </>
                    ) : post.type === 'tech_tip' ? (
                        <SidebarSection
                            title="Related Drops"
                            posts={categoryPosts}
                            emptyLabel="No related posts yet."
                        />
                    ) : (
                        <SidebarSection
                            title="Recent Drops"
                            posts={recentPosts}
                            emptyLabel="No other posts yet."
                        />
                    )}

                    {/* Ad unit — between recent and related sections */}
                    <div className="min-h-[250px]">
                        <AdUnit slot="YOUR_AD_SLOT_ID" />
                    </div>

                    {post.type === 'tech_news' ? (
                        <SidebarSection
                            title="From Same Tags"
                            posts={tagPosts}
                            emptyLabel={null}
                            rose
                        />
                    ) : post.type === 'tech_tip' ? (
                        <SidebarSection
                            title="From Same Tags"
                            posts={tagPosts}
                            emptyLabel={null}
                        />
                    ) : (
                        <>
                            <SidebarSection
                                title="Related Drops"
                                posts={categoryPosts}
                                emptyLabel={null}
                            />
                            <SidebarSection
                                title="From Same Tags"
                                posts={tagPosts}
                                emptyLabel={null}
                            />
                        </>
                    )}
                </aside>
            </div>
        </PublicLayout>
        </>
    );
}

function BodyWithImages({ body, images, fits = [] }) {
    const paragraphs = body ? body.split(/\n\n+/) : [];
    const total = paragraphs.length;

    const cut1 = Math.max(1, Math.floor(total / 3));
    const cut2 = Math.max(cut1 + 1, Math.floor((2 * total) / 3));

    const sections = [
        { text: paragraphs.slice(0, cut1).join('\n\n'), image: images[0], fit: fits[0] ?? 'cover' },
        { text: paragraphs.slice(cut1, cut2).join('\n\n'), image: images[1], fit: fits[1] ?? 'cover' },
        { text: paragraphs.slice(cut2).join('\n\n'), image: images[2], fit: fits[2] ?? 'cover' },
    ];

    return (
        <div className="post-body">
            {sections.map((section, i) => (
                <div key={i}>
                    {section.text && (
                        <div className="prose prose-gray max-w-none">
                            <ReactMarkdown components={proseComponents}>{section.text}</ReactMarkdown>
                        </div>
                    )}
                    {section.image && (
                        <AdaptiveImage
                            src={section.image}
                            alt=""
                            fit={section.fit}
                            className="w-full rounded-xl max-h-80"
                            wrapperClass="my-8"
                        />
                    )}
                </div>
            ))}
        </div>
    );
}

function ReadingProgress() {
    const [progress, setProgress] = useState(0);

    useEffect(() => {
        function update() {
            const doc = document.documentElement;
            const total = doc.scrollHeight - doc.clientHeight;
            setProgress(total > 0 ? (doc.scrollTop / total) * 100 : 0);
        }
        window.addEventListener('scroll', update, { passive: true });
        return () => window.removeEventListener('scroll', update);
    }, []);

    return (
        <div className="fixed top-0 left-0 right-0 h-1 z-[60] bg-transparent pointer-events-none">
            <div
                className="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-400"
                style={{ width: `${progress}%` }}
            />
        </div>
    );
}

function RelatedProductsSection({ products, postId }) {
    return (
        <div>
            <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">
                Shop Related
            </h3>
            <ul className="space-y-3">
                {products.map((p) => (
                    <li key={p.id}>
                        <a href={route('affiliate.redirect', { product: p.id, post: postId })}
                            target="_blank" rel="nofollow sponsored"
                            className="flex items-center gap-3 group">
                            {p.image_url ? (
                                <img src={p.image_url} alt={p.name} loading="lazy"
                                    className="w-12 h-12 rounded-lg object-contain bg-gray-50 flex-shrink-0" />
                            ) : (
                                <div className="w-12 h-12 rounded-lg bg-orange-50 flex-shrink-0 flex items-center justify-center">
                                    <span className="text-orange-300 text-lg font-bold">A</span>
                                </div>
                            )}
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-gray-800 group-hover:text-orange-600 leading-snug transition-colors line-clamp-2">
                                    {p.name}
                                </p>
                                {p.price && (
                                    <p className="text-xs text-gray-500 mt-0.5">${p.price}</p>
                                )}
                            </div>
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function SidebarSection({ title, posts, emptyLabel, rose = false }) {
    if (!posts || (posts.length === 0 && !emptyLabel)) return null;

    const hoverColor = rose ? 'group-hover:text-rose-600' : 'group-hover:text-indigo-600';
    const placeholderBg = rose ? 'bg-rose-50' : 'bg-indigo-50';
    const placeholderText = rose ? 'text-rose-300' : 'text-indigo-300';

    return (
        <div>
            <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">
                {title}
            </h3>
            {posts.length === 0 ? (
                <p className="text-sm text-gray-400">{emptyLabel}</p>
            ) : (
                <ul className="space-y-4">
                    {posts.map((p) => (
                        <li key={p.id} className="group">
                            <Link href={route('posts.show', p.slug)} className="flex gap-3 items-start">
                                {p.featured_image ? (
                                    <img
                                        src={p.featured_image}
                                        alt={p.title}
                                        loading="lazy"
                                        className="w-14 h-14 rounded-lg object-cover flex-shrink-0 bg-gray-100"
                                    />
                                ) : (
                                    <div className={`w-14 h-14 rounded-lg ${placeholderBg} flex-shrink-0 flex items-center justify-center`}>
                                        <span className={`${placeholderText} text-xl font-bold`}>G</span>
                                    </div>
                                )}
                                <div className="min-w-0">
                                    <p className={`text-sm font-medium text-gray-800 ${hoverColor} leading-snug transition-colors`}>
                                        {p.title}
                                    </p>
                                    <p className="text-xs text-gray-400 mt-0.5">{p.published_at}</p>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/* ── Helpers ── */

function isBreakingNews(isoDate) {
    if (!isoDate) return false;
    return Date.now() - new Date(isoDate).getTime() < 24 * 60 * 60 * 1000;
}

function extractSourceDomain(url) {
    if (!url) return url;
    try { return new URL(url).hostname.replace(/^www\./, ''); } catch { return url; }
}

function ProductCard({ product, postId }) {
    return (
        <div className="flex gap-4 bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            {product.image_url && (
                <img src={product.image_url} alt={`Product photo: ${product.name}`} loading="lazy"
                    className="w-32 h-32 object-contain rounded-lg flex-shrink-0" />
            )}
            <div className="flex-1 min-w-0">
                <h3 className="font-semibold text-gray-900 leading-snug">{product.name}</h3>
                {product.description && <p className="text-sm text-gray-500 mt-1 line-clamp-2">{product.description}</p>}
                <div className="mt-4">
                    {product.price && (
                        <p className="text-2xl font-bold text-gray-900 mb-2">${product.price}</p>
                    )}
                    <a href={route('affiliate.redirect', { product: product.id, post: postId })}
                        target="_blank" rel="nofollow sponsored"
                        className="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-3 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2">
                        View on Amazon →
                    </a>
                </div>
            </div>
        </div>
    );
}
