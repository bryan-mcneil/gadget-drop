import { Head, Link, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';

const THEME = {
    bg:          'linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)',
    heroBg:      '#030309',
    spotlightBg: 'linear-gradient(160deg, #050510 0%, #0c0c1d 45%, #0e0b1f 100%)',
    heroOverlay: 'from-gray-950/95 via-gray-950/75 to-gray-950/30',
    heroFallback:'from-gray-950 to-indigo-950',
    dotColor:    'rgba(99,102,241,0.18)',
    dotSize:     '28px 28px',
    accentVia:   'via-indigo-500/40',
    accentBot:   'via-indigo-500/20',
    glowA:       'bg-indigo-700/15',
    glowB:       'bg-purple-700/10',
    spotAccent:  'via-indigo-500/50',
};

export default function Home({ heroSlides, recentPosts, categories, spotlight, topPicks }) {
    const { navigation } = usePage().props;
    const popularTags = navigation?.popularTags ?? [];
    const latestNews  = navigation?.latestNews  ?? [];
    const tools       = navigation?.tools       ?? [];
    const theme = THEME;

    return (
        <PublicLayout>
            <Head title="GadgetDrop — Daily Tech Picks">
                <meta name="description" content="Daily tech picks, gadget reviews, and buying guides. Find the best gear at the best price — delivered fresh every day." />
            </Head>

            <HeroCarousel slides={heroSlides} theme={theme} />

            <CategoriesStrip categories={categories} theme={theme} />

            <TopPicks picks={topPicks} />

            <FeaturedSpotlight spotlight={spotlight} theme={theme} />

            {/* Recent Drops section */}
            <div className="relative">
                {/* Subtle top accent */}
                <div className="h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent" />

                <div className="max-w-6xl mx-auto px-4 py-12 grid grid-cols-1 lg:grid-cols-4 gap-10">
                    <main className="lg:col-span-3">
                        {/* Section header */}
                        <div className="flex items-center justify-between mb-8">
                            <div className="flex items-center gap-3">
                                <span className="w-1 h-7 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500" />
                                <div>
                                    <h2 className="text-2xl font-extrabold text-gray-900 leading-none">Recent Drops</h2>
                                    <p className="text-xs text-gray-400 mt-0.5 tracking-wide">The latest from GadgetDrop</p>
                                </div>
                            </div>
                            <Link href={route('search')}
                                className="text-sm text-indigo-600 hover:text-indigo-700 font-semibold flex items-center gap-1 transition-colors">
                                View all
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            {recentPosts.map((post) => (
                                <PostCard key={post.id} post={post} />
                            ))}
                        </div>
                    </main>

                    {/* Sidebar */}
                    <aside className="space-y-5">
                        {/* Categories */}
                        <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                            <h3 className="flex items-center gap-2 font-bold text-gray-800 text-xs uppercase tracking-widest mb-4">
                                <span className="w-3 h-px bg-indigo-400" />
                                Categories
                            </h3>
                            <ul className="space-y-1">
                                {categories.map((cat) => (
                                    <li key={cat.id}>
                                        <Link href={route('category', cat.slug)}
                                            className="flex items-center justify-between group py-1 text-sm text-gray-700 hover:text-indigo-600 transition-colors">
                                            <span>{cat.name}</span>
                                            <svg className="w-3.5 h-3.5 text-gray-300 group-hover:text-indigo-400 transition-colors"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Tags */}
                        {popularTags.length > 0 && (
                            <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                                <h3 className="flex items-center gap-2 font-bold text-gray-800 text-xs uppercase tracking-widest mb-4">
                                    <span className="w-3 h-px bg-indigo-400" />
                                    Popular Tags
                                </h3>
                                <div className="flex flex-wrap gap-2">
                                    {popularTags.slice(0, 20).map((tag) => (
                                        <Link key={tag.id}
                                            href={`${route('search')}?q=${encodeURIComponent(tag.name)}`}
                                            className="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full font-medium transition-colors">
                                            #{tag.name}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                    </aside>
                </div>
            </div>

            <BreakingNewsSection posts={latestNews} theme={theme} />

            <ToolsSection tools={tools} />

            <JoinTheDrop />
        </PublicLayout>
    );
}

/* ── Hero Carousel ──────────────────────────────────────────── */
function HeroCarousel({ slides, theme }) {
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const count = slides.length;

    const next = useCallback(() => setActive(i => (i + 1) % count), [count]);
    const prev = useCallback(() => setActive(i => (i - 1 + count) % count), [count]);

    useEffect(() => {
        if (paused || count <= 1) return;
        const t = setInterval(next, 6000);
        return () => clearInterval(t);
    }, [paused, count, next]);

    if (!slides.length) return null;

    return (
        <section
            className="relative overflow-hidden min-h-[460px] md:min-h-[520px] text-white"
            style={{ background: theme?.heroBg ?? '#030309' }}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
        >
            {/* Slides */}
            {slides.map((slide, i) => (
                <div
                    key={i}
                    className={`absolute inset-0 transition-opacity duration-700 ${
                        i === active ? 'opacity-100 z-10' : 'opacity-0 z-0'
                    }`}
                    aria-hidden={i !== active}
                >
                    {/* Background image + gradient overlay */}
                    {slide.post.featured_image ? (
                        <>
                            <img
                                src={slide.post.hero_image ?? slide.post.featured_image}
                                alt=""
                                className="absolute inset-0 w-full h-full object-cover"
                                style={{ objectPosition: slide.post.hero_image_position ?? slide.post.featured_image_position ?? 'center center' }}
                            />
                            <div className={`absolute inset-0 bg-gradient-to-r ${theme?.heroOverlay ?? 'from-gray-950/95 via-gray-950/75 to-gray-950/30'}`} />
                        </>
                    ) : (
                        <div className={`absolute inset-0 bg-gradient-to-br ${theme?.heroFallback ?? 'from-gray-950 to-indigo-950'}`} />
                    )}

                    {/* Content */}
                    <div className="relative z-10 h-full flex flex-col justify-center max-w-4xl mx-auto px-6 py-16">
                        {slide.post.type === 'tech_news' ? (
                            <span className="inline-flex items-center gap-1.5 text-rose-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                                <span className="relative flex h-2 w-2 mr-0.5">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75" />
                                    <span className="relative inline-flex rounded-full h-2 w-2 bg-rose-500" />
                                </span>
                                {slide.label}
                            </span>
                        ) : slide.post.type === 'tech_tip' ? (
                            <span className="inline-flex items-center gap-1.5 text-emerald-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                {slide.label}
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 text-indigo-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                                <span className="w-4 h-px bg-indigo-400" />
                                {slide.label}
                            </span>
                        )}
                        <h1 className="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight max-w-2xl">
                            {slide.post.title}
                        </h1>
                        {slide.post.excerpt && (
                            <p className="mt-4 text-gray-300 text-base md:text-lg leading-relaxed max-w-xl line-clamp-2">
                                {slide.post.excerpt}
                            </p>
                        )}
                        <div className="mt-7 flex items-center gap-4">
                            <Link
                                href={route('posts.show', slide.post.slug)}
                                className={`inline-flex items-center gap-2 text-white font-semibold px-6 py-3 rounded-lg transition-colors ${
                                    slide.post.type === 'tech_news' ? 'bg-rose-600 hover:bg-rose-500'
                                    : slide.post.type === 'tech_tip' ? 'bg-emerald-600 hover:bg-emerald-500'
                                    : 'bg-indigo-600 hover:bg-indigo-500'
                                }`}
                            >
                                {slide.post.type === 'tech_news' ? 'Read the Story'
                                    : slide.post.type === 'tech_tip' ? 'Read the Tip'
                                    : 'Read the Drop'}
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                            {slide.post.published_at && (
                                <span className="text-sm text-gray-400">{slide.post.published_at}</span>
                            )}
                        </div>
                    </div>
                </div>
            ))}

            {/* Prev / Next arrows */}
            {count > 1 && (
                <>
                    <button
                        onClick={prev}
                        aria-label="Previous slide"
                        className="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/10 hover:bg-white/25 backdrop-blur-sm flex items-center justify-center transition-colors"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button
                        onClick={next}
                        aria-label="Next slide"
                        className="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/10 hover:bg-white/25 backdrop-blur-sm flex items-center justify-center transition-colors"
                    >
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </>
            )}

            {/* Dot indicators */}
            {count > 1 && (
                <div className="absolute bottom-5 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2">
                    {slides.map((slide, i) => (
                        <button
                            key={i}
                            onClick={() => setActive(i)}
                            aria-label={`Go to slide ${i + 1}: ${slide.label}`}
                            className={`h-1.5 rounded-full transition-all duration-300 ${
                                i === active
                                    ? 'w-8 bg-indigo-400'
                                    : 'w-2 bg-white/35 hover:bg-white/60'
                            }`}
                        />
                    ))}
                </div>
            )}

            {/* Slide counter (top-right) */}
            {count > 1 && (
                <div className="absolute top-4 right-4 z-20 text-xs text-white/50 tabular-nums">
                    {active + 1} / {count}
                </div>
            )}
        </section>
    );
}

/* ── Tools Section ──────────────────────────────────────────── */
const TOOL_ICONS = {
    'code-bracket': (
        <svg className="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
        </svg>
    ),
    scissors: (
        <svg className="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="m7.848 8.25 1.536.887M7.848 8.25a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l2.077 1.199M7.848 15.75l1.536-.887m-1.536.887a3 3 0 1 1-5.196 3 3 3 0 0 1 5.196-3Zm1.536-.887a2.165 2.165 0 0 0 1.083-1.838c.005-.352.054-.695.14-1.025m-1.223 2.863 2.077-1.199m0-3.328a4.323 4.323 0 0 1 2.068-1.379l5.325-1.628a4.5 4.5 0 0 1 2.48-.044l.803.215-7.794 4.5m-2.882-1.664A4.331 4.331 0 0 0 10.607 12m3.736 0 7.794 4.5-.802.215a4.5 4.5 0 0 1-2.48-.043l-5.326-1.629a4.324 4.324 0 0 1-2.068-1.379M14.343 12l-2.882 1.664" />
        </svg>
    ),
    photo: (
        <svg className="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
    ),
    crop: (
        <svg className="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 3.75v13.5m0 0H21m-13.5 0H3M20.25 7.5H6.75" />
        </svg>
    ),
    sparkles: (
        <svg className="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
        </svg>
    ),
    wrench: (
        <svg className="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" />
        </svg>
    ),
};

const TOOL_PREVIEWS = {
    'json-validator': (
        <div className="rounded-xl bg-gray-950 border border-gray-800 p-4 font-mono text-xs leading-relaxed overflow-hidden select-none">
            <div className="flex items-center gap-1.5 mb-3">
                <span className="w-2.5 h-2.5 rounded-full bg-red-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-yellow-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-green-500/60" />
                <span className="ml-2 text-gray-600 text-xs">json-validator</span>
            </div>
            <div className="space-y-0.5">
                <p><span className="text-gray-500">{'{'}</span></p>
                <p className="pl-4"><span className="text-amber-400">"name"</span><span className="text-gray-400">: </span><span className="text-green-400">"GadgetDrop"</span><span className="text-gray-500">,</span></p>
                <p className="pl-4"><span className="text-amber-400">"version"</span><span className="text-gray-400">: </span><span className="text-blue-400">2</span><span className="text-gray-500">,</span></p>
                <p className="pl-4"><span className="text-amber-400">"tools"</span><span className="text-gray-400">: </span><span className="text-gray-500">[</span></p>
                <p className="pl-8"><span className="text-green-400">"json-validator"</span><span className="text-gray-500">,</span></p>
                <p className="pl-8"><span className="text-green-400">"js-css-minifier"</span></p>
                <p className="pl-4"><span className="text-gray-500">]</span></p>
                <p><span className="text-gray-500">{'}'}</span></p>
            </div>
            <div className="mt-3 flex items-center gap-2 text-green-400">
                <svg className="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>Valid JSON, formatted successfully.</span>
            </div>
        </div>
    ),
    'js-css-minifier': (
        <div className="rounded-xl bg-gray-950 border border-gray-800 p-4 font-mono text-xs leading-relaxed overflow-hidden select-none">
            <div className="flex items-center gap-1.5 mb-3">
                <span className="w-2.5 h-2.5 rounded-full bg-red-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-yellow-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-green-500/60" />
                <span className="ml-2 text-gray-600 text-xs">js-minifier</span>
            </div>
            <div className="space-y-1">
                <p className="text-gray-500 text-xs uppercase tracking-widest mb-2">Before</p>
                <p className="text-gray-400 line-clamp-2">{'function greet(name) {'}</p>
                <p className="text-gray-400 pl-4">{'  return "Hello, " + name + "!";'}</p>
                <p className="text-gray-400">{'}'}</p>
            </div>
            <div className="my-3 flex items-center gap-2">
                <span className="flex-1 h-px bg-gray-800" />
                <span className="text-xs text-gray-600">minified</span>
                <span className="flex-1 h-px bg-gray-800" />
            </div>
            <p className="text-green-400">{'function greet(n){return"Hello, "+n+"!"}'}</p>
            <div className="mt-3 flex items-center gap-2">
                <span className="text-xs font-semibold bg-green-900/50 text-green-400 px-2 py-0.5 rounded-full border border-green-800/50">
                    Saved 28%
                </span>
                <span className="text-gray-600 text-xs">68 B → 49 B</span>
            </div>
        </div>
    ),
    'image-converter': (
        <div className="rounded-xl bg-gray-950 border border-gray-800 p-4 overflow-hidden select-none">
            <div className="flex items-center gap-1.5 mb-3">
                <span className="w-2.5 h-2.5 rounded-full bg-red-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-yellow-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-green-500/60" />
                <span className="ml-2 text-gray-600 text-xs">image-converter</span>
            </div>
            <div className="flex gap-3 items-center mb-3">
                <div className="w-20 h-16 rounded-lg bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-600">
                    <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z" />
                    </svg>
                </div>
                <div className="flex-1 space-y-2">
                    <div className="flex gap-1.5">
                        {['WebP', 'JPG', 'PNG'].map((f, i) => (
                            <span key={f} className={`text-xs px-2 py-1 rounded-lg font-semibold ${i === 0 ? 'bg-amber-500 text-white' : 'bg-gray-800 text-gray-500'}`}>{f}</span>
                        ))}
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-gray-500">Quality</span>
                        <div className="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                            <div className="h-full w-[90%] bg-amber-500 rounded-full" />
                        </div>
                        <span className="text-xs text-amber-400 font-bold">90%</span>
                    </div>
                </div>
            </div>
            <div className="flex items-center gap-2 text-xs">
                <span className="text-gray-500">photo.jpg 2.4 MB</span>
                <svg className="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
                <span className="text-green-400 font-semibold">photo.webp 0.8 MB</span>
                <span className="ml-auto text-xs font-bold bg-green-900/50 text-green-400 px-2 py-0.5 rounded-full border border-green-800/50">-67%</span>
            </div>
        </div>
    ),
    'image-cropper': (
        <div className="rounded-xl bg-gray-950 border border-gray-800 p-4 overflow-hidden select-none">
            <div className="flex items-center gap-1.5 mb-3">
                <span className="w-2.5 h-2.5 rounded-full bg-red-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-yellow-500/60" />
                <span className="w-2.5 h-2.5 rounded-full bg-green-500/60" />
                <span className="ml-2 text-gray-600 text-xs">image-cropper</span>
            </div>
            <div className="flex gap-1.5 mb-3">
                {['Free', '1:1', '16:9', '4:3', 'Circle'].map((p, i) => (
                    <span key={p} className={`text-xs px-2 py-1 rounded-lg font-semibold ${i === 2 ? 'bg-amber-500 text-white' : 'bg-gray-800 text-gray-500'}`}>{p}</span>
                ))}
            </div>
            <div className="relative bg-gray-900 rounded-lg overflow-hidden h-20 flex items-center justify-center border border-gray-700">
                <div className="absolute inset-0 opacity-30"
                    style={{ backgroundImage: 'linear-gradient(45deg, #374151 25%, transparent 25%), linear-gradient(-45deg, #374151 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #374151 75%), linear-gradient(-45deg, transparent 75%, #374151 75%)', backgroundSize: '8px 8px', backgroundPosition: '0 0, 0 4px, 4px -4px, -4px 0px' }} />
                <div className="relative border-2 border-amber-400 w-28 h-16 rounded-sm">
                    <div className="absolute -top-1 -left-1 w-3 h-3 border-t-2 border-l-2 border-amber-400" />
                    <div className="absolute -top-1 -right-1 w-3 h-3 border-t-2 border-r-2 border-amber-400" />
                    <div className="absolute -bottom-1 -left-1 w-3 h-3 border-b-2 border-l-2 border-amber-400" />
                    <div className="absolute -bottom-1 -right-1 w-3 h-3 border-b-2 border-r-2 border-amber-400" />
                </div>
            </div>
        </div>
    ),
};

function ToolsSection({ tools }) {
    if (!tools?.length) return null;

    const featured = tools[0];
    const rest = tools.slice(1);
    const featuredIcon = TOOL_ICONS[featured.icon] ?? TOOL_ICONS['wrench'];
    const featuredPreview = TOOL_PREVIEWS[featured.slug] ?? null;

    return (
        <section className="bg-gray-100 py-16 relative overflow-hidden">
            {/* Subtle top/bottom borders */}
            <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-200 to-transparent" />
            <div className="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-amber-200 to-transparent" />

            <div className="max-w-6xl mx-auto px-4">
                {/* Section header */}
                <div className="flex items-center justify-between mb-8">
                    <div className="flex items-center gap-3">
                        <span className="w-1 h-7 rounded-full bg-gradient-to-b from-amber-400 to-orange-400" />
                        <div>
                            <h2 className="text-2xl font-extrabold text-gray-900 leading-none">Free Online Tools</h2>
                            <p className="text-xs text-gray-400 mt-0.5 tracking-wide">Runs in your browser, nothing sent to a server</p>
                        </div>
                    </div>
                    <Link href={route('tools.index')}
                        className="text-sm text-amber-600 hover:text-amber-700 font-semibold flex items-center gap-1 transition-colors">
                        All tools
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                {/* Featured + aside layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

                    {/* Featured tool — spans 2 columns */}
                    <div className="lg:col-span-2 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-300">
                        <div className="p-7 flex flex-col h-full">
                            {/* Badge */}
                            <div className="flex items-center gap-2 mb-5">
                                <span className="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" />
                                    </svg>
                                    Featured Tool
                                </span>
                            </div>

                            {/* Icon + title */}
                            <div className="flex items-start gap-4 mb-4">
                                <div className="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex-shrink-0 flex items-center justify-center p-2.5 text-amber-600">
                                    {featuredIcon}
                                </div>
                                <div>
                                    <h3 className="text-xl font-extrabold text-gray-900">{featured.name}</h3>
                                    <p className="text-sm text-gray-500 mt-0.5 leading-relaxed">{featured.description}</p>
                                </div>
                            </div>

                            {/* Mock preview */}
                            {featuredPreview && (
                                <div className="flex-1 mb-6">
                                    {featuredPreview}
                                </div>
                            )}

                            {/* CTA */}
                            <Link
                                href={route(`tools.${featured.slug}`)}
                                className="inline-flex items-center gap-2 self-start bg-amber-500 hover:bg-amber-600
                                           text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
                                Use Tool Free
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </Link>
                        </div>
                    </div>

                    {/* Aside — remaining tools stacked */}
                    <div className="flex flex-col gap-4">
                        {/* Other tools */}
                        {rest.map(tool => {
                            const icon = TOOL_ICONS[tool.icon] ?? TOOL_ICONS['wrench'];
                            return (
                                <Link
                                    key={tool.slug}
                                    href={route(`tools.${tool.slug}`)}
                                    className="group flex gap-4 items-start bg-white border border-gray-200 rounded-2xl p-5
                                               hover:border-amber-300 hover:shadow-md transition-all duration-200 shadow-sm">
                                    <div className="w-10 h-10 rounded-xl bg-amber-50 border border-amber-100 flex-shrink-0
                                                    flex items-center justify-center p-2 text-amber-600 group-hover:bg-amber-100 transition-colors">
                                        {icon}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="font-bold text-gray-900 group-hover:text-amber-700 transition-colors text-sm">{tool.name}</p>
                                        <p className="text-xs text-gray-500 mt-0.5 leading-relaxed line-clamp-2">{tool.description}</p>
                                    </div>
                                    <svg className="w-4 h-4 text-gray-300 group-hover:text-amber-500 flex-shrink-0 mt-0.5 transition-colors"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            );
                        })}

                        {/* "View all tools" card — always visible */}
                        <Link
                            href={route('tools.index')}
                            className="group flex gap-4 items-center bg-amber-50 border border-amber-200 border-dashed rounded-2xl p-5
                                       hover:bg-amber-100 hover:border-amber-300 transition-all duration-200">
                            <div className="w-10 h-10 rounded-xl bg-white border border-amber-200 flex-shrink-0
                                            flex items-center justify-center text-amber-500 group-hover:bg-amber-50 transition-colors">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                            <div className="min-w-0 flex-1">
                                                <p className="font-bold text-amber-700 text-sm">More tools coming</p>
                                <p className="text-xs text-amber-600/70 mt-0.5">Color picker, Base64 encoder, and more</p>
                            </div>
                            <svg className="w-4 h-4 text-amber-400 group-hover:text-amber-600 flex-shrink-0 transition-colors"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

/* ── Join the Drop ──────────────────────────────────────────── */
function JoinTheDrop() {
    const [email, setEmail]   = useState('');
    const [status, setStatus] = useState(null); // null | 'loading' | 'success' | 'duplicate' | 'error'
    const inputRef = useRef(null);

    async function handleSubmit(e) {
        e.preventDefault();
        if (status === 'loading') return;
        setStatus('loading');

        try {
            const res = await fetch(route('subscribe'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ email }),
            });

            if (res.status === 409) {
                setStatus('duplicate');
            } else if (res.ok) {
                setStatus('success');
                setEmail('');
            } else {
                setStatus('error');
            }
        } catch {
            setStatus('error');
        }
    }

    return (
        <section
            className="relative py-20 overflow-hidden"
            style={{ background: 'linear-gradient(135deg, #312e81 0%, #4338ca 45%, #6d28d9 100%)' }}
        >
            {/* Concentric decorative rings */}
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-full border border-white/[0.04] pointer-events-none" />
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[480px] h-[480px] rounded-full border border-white/[0.06] pointer-events-none" />
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[260px] h-[260px] rounded-full border border-white/[0.08] pointer-events-none" />

            {/* Dot-grid texture */}
            <div
                className="absolute inset-0 pointer-events-none"
                style={{
                    backgroundImage: 'radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px)',
                    backgroundSize: '30px 30px',
                }}
            />

            {/* Top + bottom accent lines */}
            <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />
            <div className="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent" />

            <div className="relative max-w-xl mx-auto px-4 text-center">
                {/* Icon */}
                <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/15 mb-6">
                    <svg className="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                </div>

                <h2 className="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-3">
                    Join the Drop
                </h2>
                <p className="text-indigo-200 text-lg mb-10 leading-relaxed">
                    Weekly tech picks, curated just for you.<br />
                    Zero spam — unsubscribe anytime.
                </p>

                {status === 'success' ? (
                    <div className="flex flex-col items-center gap-3">
                        <div className="w-16 h-16 rounded-full bg-white/15 border border-white/20 flex items-center justify-center">
                            <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <p className="text-2xl font-bold text-white mt-1">You're in!</p>
                        <p className="text-indigo-200 text-base">Watch your inbox for our weekly drop.</p>
                        <a href={route('unsubscribe')}
                            className="text-xs text-white/30 hover:text-white/60 transition-colors mt-2 inline-block">
                            Unsubscribe anytime →
                        </a>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="flex gap-2 max-w-md mx-auto">
                        <input
                            ref={inputRef}
                            type="email"
                            value={email}
                            onChange={(e) => { setEmail(e.target.value); setStatus(null); }}
                            placeholder="your@email.com"
                            required
                            className="flex-1 px-4 py-3 rounded-xl text-sm text-gray-900 placeholder-gray-400
                                       bg-white/95 border-0 focus:outline-none focus:ring-2 focus:ring-white/40 shadow-lg"
                        />
                        <button
                            type="submit"
                            disabled={status === 'loading'}
                            className="flex-shrink-0 px-6 py-3 bg-white text-indigo-700 font-bold text-sm
                                       rounded-xl hover:bg-indigo-50 transition-colors disabled:opacity-60
                                       whitespace-nowrap shadow-lg"
                        >
                            {status === 'loading' ? 'Joining…' : 'Get the Drop'}
                        </button>
                    </form>
                )}

                {status === 'duplicate' && (
                    <p className="mt-4 text-indigo-200 text-sm">You're already on the list — stay tuned!</p>
                )}
                {status === 'error' && (
                    <p className="mt-4 text-red-300 text-sm">Something went wrong — please try again.</p>
                )}

                <p className="mt-8 text-xs text-white/30">
                    We respect your privacy. Only the latest in tech products, no data selling, ever.
                </p>
            </div>
        </section>
    );
}

/* ── Top Picks strip ────────────────────────────────────────── */
function TopPicks({ picks }) {
    if (!picks?.length) return null;

    return (
        <section className="relative bg-slate-50 py-14 overflow-hidden">
            {/* Top separator */}
            <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent" />
            {/* Decorative blobs */}
            <div className="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-indigo-100/70 blur-3xl pointer-events-none" />
            <div className="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-purple-100/60 blur-3xl pointer-events-none" />

            <div className="relative max-w-6xl mx-auto px-4">
                {/* Header */}
                <div className="flex items-center gap-3 mb-8">
                    <span className="w-6 h-px bg-indigo-500" />
                    <h2 className="text-xs font-bold text-indigo-600 uppercase tracking-[0.2em]">
                        This Week's Top Picks
                    </h2>
                    <span className="flex-1 h-px bg-gradient-to-r from-indigo-200 to-transparent" />
                </div>

                <div className="flex gap-4 overflow-x-auto scrollbar-hide pb-2">
                    {picks.map((product) => (
                        <div
                            key={product.id}
                            className="group flex-shrink-0 w-52 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col"
                        >
                            {/* Image area */}
                            <div className="relative bg-gray-50 flex items-center justify-center h-44 overflow-hidden">
                                {product.image_url ? (
                                    <img
                                        src={product.image_url}
                                        alt={product.name}
                                        loading="lazy"
                                        className="max-h-36 w-auto object-contain p-3 transition-transform duration-500 group-hover:scale-105"
                                    />
                                ) : (
                                    <div className="w-full h-full bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center">
                                        <span className="text-indigo-200 font-black text-6xl select-none">G</span>
                                    </div>
                                )}
                                {product.price && (
                                    <span className="absolute top-2 right-2 bg-indigo-600 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow">
                                        ${parseFloat(product.price).toFixed(2)}
                                    </span>
                                )}
                            </div>

                            {/* Body */}
                            <div className="p-4 flex flex-col flex-1">
                                <h3 className="text-sm font-bold text-gray-900 leading-snug line-clamp-2 flex-1">
                                    {product.name}
                                </h3>

                                <div className="mt-4 space-y-2">
                                    <a
                                        href={route('affiliate.redirect', product.id)}
                                        target="_blank"
                                        rel="nofollow noopener"
                                        className="flex items-center justify-center gap-1.5 w-full py-2 rounded-lg
                                                   text-xs font-bold text-gray-900 transition-all duration-200
                                                   hover:scale-[1.02] shadow-sm hover:shadow-amber-300/50"
                                        style={{ background: 'linear-gradient(135deg, #FFB84D 0%, #FF9900 100%)' }}
                                    >
                                        View on Amazon
                                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    {product.post_slug && (
                                        <Link
                                            href={route('posts.show', product.post_slug)}
                                            className="flex items-center justify-center gap-1 w-full py-1.5
                                                       text-xs font-medium text-indigo-600 hover:text-indigo-700 transition-colors"
                                        >
                                            Read the Drop
                                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

/* ── Featured Spotlight ─────────────────────────────────────── */
function FeaturedSpotlight({ spotlight, theme }) {
    if (!spotlight) return null;

    const { post, product } = spotlight;

    return (
        <section
            className="relative py-16 overflow-hidden"
            style={{ background: theme?.spotlightBg ?? 'linear-gradient(160deg, #050510 0%, #0c0c1d 45%, #0e0b1f 100%)' }}
        >
            {/* Glow orbs */}
            <div className={`absolute left-0 top-1/2 -translate-y-1/2 w-[480px] h-[480px] rounded-full ${theme?.glowA ?? 'bg-indigo-700/15'} blur-[100px] pointer-events-none`} />
            <div className={`absolute right-0 bottom-0 w-72 h-72 rounded-full ${theme?.glowB ?? 'bg-purple-700/10'} blur-[80px] pointer-events-none`} />
            {/* Top + bottom accent lines */}
            <div className={`absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent ${theme?.spotAccent ?? 'via-indigo-500/50'} to-transparent`} />
            <div className={`absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent ${theme?.accentBot ?? 'via-indigo-500/25'} to-transparent`} />

            <div className="relative max-w-6xl mx-auto px-4">
                {/* Section label */}
                <div className="flex items-center gap-3 mb-10">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-400" />
                    </span>
                    <span className="text-xs font-bold text-indigo-400 uppercase tracking-[0.2em]">
                        Editor's Pick
                    </span>
                    <span className="flex-1 h-px bg-gradient-to-r from-indigo-500/40 to-transparent" />
                </div>

                <div className="grid md:grid-cols-5 gap-10 items-center">
                    {/* Product image */}
                    <div className="md:col-span-2 relative">
                        <div className="absolute inset-0 rounded-3xl bg-indigo-600/20 blur-2xl scale-90 pointer-events-none" />
                        <div className="relative rounded-3xl overflow-hidden border border-white/5 bg-white/[0.04] backdrop-blur-sm p-8 flex items-center justify-center min-h-[260px]">
                            {product.image_url ? (
                                <img
                                    src={product.image_url}
                                    alt={product.name}
                                    className="max-h-56 w-auto object-contain drop-shadow-2xl"
                                />
                            ) : (
                                <span className="text-white/10 font-black text-[8rem] leading-none select-none">G</span>
                            )}
                        </div>
                    </div>

                    {/* Content */}
                    <div className="md:col-span-3 space-y-5">
                        <p className="text-xs font-semibold text-gray-500 uppercase tracking-widest truncate">
                            Featured in: {post.title}
                        </p>
                        <h2 className="text-3xl lg:text-4xl font-extrabold text-white leading-tight">
                            {product.name}
                        </h2>
                        {product.description && (
                            <p className="text-gray-400 leading-relaxed line-clamp-3 text-base">
                                {product.description}
                            </p>
                        )}
                        {product.price && (
                            <div className="flex items-baseline gap-2">
                                <span className="text-3xl font-black text-white">
                                    ${parseFloat(product.price).toFixed(2)}
                                </span>
                                <span className="text-sm text-gray-500">on Amazon</span>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center gap-3 pt-1">
                            <Link
                                href={route('posts.show', post.slug)}
                                className="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white
                                           font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm border border-white/10"
                            >
                                Read the Drop
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                            <a
                                href={route('affiliate.redirect', product.id)}
                                target="_blank"
                                rel="nofollow noopener"
                                className="inline-flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl
                                           transition-all duration-200 text-sm text-gray-900
                                           shadow-lg shadow-amber-500/20 hover:shadow-amber-400/40 hover:scale-[1.02]"
                                style={{ background: 'linear-gradient(135deg, #FFB84D 0%, #FF9900 100%)' }}
                            >
                                View on Amazon
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>

                        <p className="text-xs text-gray-600 pt-1">
                            #ad #commissionsearned — As an Amazon Associate we earn from qualifying purchases.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}

/* ── Categories strip ───────────────────────────────────────── */
function CategoriesStrip({ categories, theme }) {
    if (!categories?.length) return null;

    const scrollRef = useRef(null);
    const [canScrollLeft,  setCanScrollLeft]  = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(false);
    const [scrollProgress, setScrollProgress] = useState(0);

    const updateButtons = useCallback(() => {
        const el = scrollRef.current;
        if (!el) return;
        setCanScrollLeft(el.scrollLeft > 2);
        setCanScrollRight(Math.ceil(el.scrollLeft + el.clientWidth) < el.scrollWidth - 2);
        const maxScroll = el.scrollWidth - el.clientWidth;
        setScrollProgress(maxScroll > 0 ? (el.scrollLeft / maxScroll) * 100 : 0);
    }, []);

    useEffect(() => {
        updateButtons();
        const el = scrollRef.current;
        el?.addEventListener('scroll', updateButtons, { passive: true });
        window.addEventListener('resize', updateButtons);
        return () => {
            el?.removeEventListener('scroll', updateButtons);
            window.removeEventListener('resize', updateButtons);
        };
    }, [updateButtons]);

    function scroll(dir) {
        scrollRef.current?.scrollBy({ left: dir * 280, behavior: 'smooth' });
    }

    return (
        <section
            className="relative py-12"
            style={{ background: theme?.bg ?? 'linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)' }}
        >
            {/* Dot-grid texture */}
            <div
                className="absolute inset-0 pointer-events-none"
                style={{
                    backgroundImage: `radial-gradient(circle, ${theme?.dotColor ?? 'rgba(99,102,241,0.18)'} 1px, transparent 1px)`,
                    backgroundSize: theme?.dotSize ?? '28px 28px',
                }}
            />
            {/* Top + bottom accent lines */}
            <div className={`absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent ${theme?.accentVia ?? 'via-indigo-500/40'} to-transparent`} />
            <div className={`absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent ${theme?.accentBot ?? 'via-indigo-500/20'} to-transparent`} />

            <div className="relative max-w-[96rem] mx-auto px-4">
                <div className="flex items-center gap-3 mb-7">
                    <span className="w-6 h-px bg-indigo-500" />
                    <h2 className="text-xs font-bold text-indigo-400 uppercase tracking-[0.2em]">
                        Browse by Category
                    </h2>
                </div>

                {/* Scroll container with buttons */}
                <div className="relative">
                    {/* Left scroll button — only when scrolled right */}
                    {canScrollLeft && (
                        <button
                            onClick={() => scroll(-1)}
                            aria-label="Scroll left"
                            className="hidden md:flex absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 z-10
                                       w-10 h-10 items-center justify-center rounded-full
                                       bg-gray-900/80 border border-indigo-500/40 text-indigo-300
                                       hover:bg-indigo-600 hover:text-white hover:border-indigo-600
                                       transition-all duration-200 shadow-lg shadow-black/40 backdrop-blur-sm"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                    )}

                    {/* Right scroll button — hidden once at end */}
                    {canScrollRight && (
                        <button
                            onClick={() => scroll(1)}
                            aria-label="Scroll right"
                            className="hidden md:flex absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 z-10
                                       w-10 h-10 items-center justify-center rounded-full
                                       bg-gray-900/80 border border-indigo-500/40 text-indigo-300
                                       hover:bg-indigo-600 hover:text-white hover:border-indigo-600
                                       transition-all duration-200 shadow-lg shadow-black/40 backdrop-blur-sm"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    )}

                    {/* Scrollable strip — native touch scroll on mobile/tablet */}
                    <div
                        ref={scrollRef}
                        className="flex gap-5 overflow-x-auto scrollbar-hide pb-2"
                        style={{ touchAction: 'pan-x' }}
                    >
                        {categories.map((cat) => (
                            <Link
                                key={cat.id}
                                href={route('category', cat.slug)}
                                className="group relative flex-shrink-0 w-56 h-80 rounded-2xl overflow-hidden block shadow-lg shadow-black/40"
                            >
                                {/* Image */}
                                {cat.featured_image ? (
                                    <img
                                        src={cat.featured_image}
                                        alt={cat.name}
                                        loading="lazy"
                                        className="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                    />
                                ) : (
                                    <div className="absolute inset-0 bg-gradient-to-br from-indigo-700 to-purple-900 flex items-center justify-center">
                                        <span className="text-white/20 font-black text-8xl select-none">
                                            {cat.name.charAt(0)}
                                        </span>
                                    </div>
                                )}

                                {/* Gradient scrim */}
                                <div className="absolute inset-0 bg-gradient-to-t from-gray-950/90 via-gray-950/20 to-transparent
                                                transition-opacity duration-300 group-hover:opacity-90" />

                                {/* Name + indigo underline accent */}
                                <div className="absolute bottom-0 left-0 right-0 px-4 py-4
                                                translate-y-1 group-hover:translate-y-0 transition-transform duration-300">
                                    <p className="text-white font-bold text-base leading-tight tracking-wide">
                                        {cat.name}
                                    </p>
                                    <div className="mt-1.5 h-0.5 w-0 bg-indigo-400 rounded-full
                                                    transition-all duration-300 group-hover:w-8" />
                                </div>

                                {/* Hover border glow */}
                                <div className="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/5
                                                group-hover:ring-indigo-500/50 transition-all duration-300" />
                            </Link>
                        ))}
                    </div>

                    {/* Horizontal scroll progress bar */}
                    {/* <div className="mt-4 h-0.5 bg-indigo-900/40 rounded-full overflow-hidden">
                        <div
                            className="h-full bg-indigo-400 rounded-full transition-all duration-150 ease-out"
                            style={{ width: `${scrollProgress}%` }}
                        />
                    </div> */}
                </div>
            </div>
        </section>
    );
}

/* ── Breaking News section ──────────────────────────────────── */
function BreakingNewsSection({ posts, theme }) {
    if (!posts?.length) return null;
    const post = posts[0];
    const isBreaking = isWithin24h(post.published_at_iso);
    const hasImage = !!(post.hero_image || post.featured_image);

    return (
        <section className="relative overflow-hidden" style={{ background: theme?.bg ?? 'linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)' }}>
            {/* Rose accent bar */}
            <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rose-500 via-red-400 to-rose-600 z-10" />

            {/* Dot-grid texture */}
            <div
                className="absolute inset-0 pointer-events-none"
                style={{
                    backgroundImage: `radial-gradient(circle, ${theme?.dotColor ?? 'rgba(99,102,241,0.12)'} 1px, transparent 1px)`,
                    backgroundSize: theme?.dotSize ?? '28px 28px',
                }}
            />

            {/* Glow orbs */}
            <div className={`absolute -bottom-24 -right-24 w-80 h-80 rounded-full ${theme?.glowB ?? 'bg-rose-700/10'} blur-3xl pointer-events-none`} />
            <div className={`absolute top-0 left-1/3 w-64 h-64 rounded-full ${theme?.glowA ?? 'bg-indigo-700/10'} blur-[80px] pointer-events-none`} />

            {/* Image — CSS mask fades pixels to transparent so the dark bg bleeds through cleanly */}
            {hasImage && (
                <div className="pt-1">
                    <img
                        src={post.hero_image ?? post.featured_image}
                        alt={post.title}
                        className="w-full object-cover"
                        style={{
                            height: '460px',
                            objectPosition: post.hero_image_position ?? post.featured_image_position ?? 'center center',
                            maskImage: 'linear-gradient(to bottom, black 0%, black 20%, transparent 92%)',
                            WebkitMaskImage: 'linear-gradient(to bottom, black 0%, black 20%, transparent 92%)',
                        }}
                    />
                </div>
            )}

            {/* Text content — pulled up into the faded bottom of the image */}
            <div className={`relative z-10 max-w-6xl mx-auto px-4 pb-14 ${hasImage ? '-mt-44' : 'pt-16'}`}>

                {/* Eyebrow */}
                <div className="flex items-center gap-3 mb-4">
                    <span className="relative flex h-2.5 w-2.5">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500" />
                    </span>
                    <span className="text-rose-400 text-xs font-black uppercase tracking-[0.25em]">
                        {isBreaking ? 'Breaking News' : 'Latest Tech News'}
                    </span>
                    <span className="h-px flex-1 max-w-16 bg-gradient-to-r from-rose-500/40 to-transparent" />
                </div>

                {/* Headline */}
                <h2 className="text-3xl md:text-4xl lg:text-5xl font-extrabold text-white leading-tight max-w-3xl mb-4">
                    {post.title}
                </h2>

                {/* Excerpt */}
                {post.excerpt && (
                    <p className="text-indigo-200/60 text-base md:text-lg leading-relaxed max-w-2xl line-clamp-2 mb-5">
                        {post.excerpt}
                    </p>
                )}

                {/* Meta */}
                <div className="flex items-center gap-3 text-xs text-indigo-400/60 mb-8">
                    <span>{formatNewsDate(post.published_at)}</span>
                </div>

                {/* CTA */}
                <Link
                    href={route('posts.show', post.slug)}
                    className="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-500
                               text-white font-bold px-7 py-3 rounded-xl transition-colors
                               shadow-lg shadow-rose-950/50"
                >
                    Read the Story
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>

            {/* Bottom accent line */}
            <div className="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent" />
        </section>
    );
}

function isWithin24h(isoDate) {
    if (!isoDate) return false;
    return Date.now() - new Date(isoDate).getTime() < 24 * 60 * 60 * 1000;
}

function extractDomain(url) {
    if (!url) return null;
    try { return new URL(url).hostname.replace(/^www\./, ''); } catch { return null; }
}

function formatNewsDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/* ── Post card ──────────────────────────────────────────────── */
function PostCard({ post }) {
    return (
        <Link href={route('posts.show', post.slug)}
            className="group bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300 flex flex-col">
            {/* Image */}
            <div className="relative overflow-hidden bg-indigo-50">
                {post.featured_image ? (
                    <img
                        src={post.featured_image}
                        alt={post.title}
                        className="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-105"
                        style={{ objectPosition: post.featured_image_position ?? 'center center' }}
                    />
                ) : (
                    <div className="w-full h-48 bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                        <span className="text-indigo-300 font-black text-5xl select-none">G</span>
                    </div>
                )}
                {/* Subtle gradient scrim at bottom of image */}
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
