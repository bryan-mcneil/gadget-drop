import { useState, useRef, useEffect } from 'react';
import { Link, router, usePage } from '@inertiajs/react';

export default function PublicLayout({ children }) {
    const [activeMenu, setActiveMenu] = useState(null);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [mobileSection, setMobileSection] = useState(null);
    const [searchQuery, setSearchQuery] = useState('');
    const headerRef = useRef(null);
    const { navigation } = usePage().props;
    const { categories = [], popularTags = [], trending = [], latestTechTips = [], latestNews = [] } = navigation ?? {};

    useEffect(() => {
        function onMouseDown(e) {
            if (headerRef.current && !headerRef.current.contains(e.target)) {
                setActiveMenu(null);
            }
        }
        function onKey(e) {
            if (e.key === 'Escape') {
                setActiveMenu(null);
                setMobileOpen(false);
            }
        }
        document.addEventListener('mousedown', onMouseDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onMouseDown);
            document.removeEventListener('keydown', onKey);
        };
    }, []);

    function toggle(name) {
        setActiveMenu(prev => prev === name ? null : name);
    }

    function submitSearch(e) {
        e.preventDefault();
        const q = searchQuery.trim();
        if (q) {
            setActiveMenu(null);
            setMobileOpen(false);
            router.visit(route('search') + '?q=' + encodeURIComponent(q));
        }
    }

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col">
            <header ref={headerRef} className="bg-white border-b border-gray-200 sticky top-0 z-50">
                {/* ── Main bar ── */}
                <div className="max-w-6xl mx-auto px-4">
                    <div className="h-16 flex items-center gap-4">

                        {/* Logo */}
                        <Link href={route('home')}
                            className="font-extrabold text-xl text-gray-900 tracking-tight shrink-0 mr-2">
                            Gadget<span className="text-indigo-600">Drop</span>
                        </Link>

                        {/* Desktop nav items */}
                        <nav className="hidden md:flex items-center gap-1">
                            <NavButton label="Categories" active={activeMenu === 'categories'}
                                onClick={() => toggle('categories')} />
                            <NavButton label="Tags"       active={activeMenu === 'tags'}
                                onClick={() => toggle('tags')} />
                            <NavButton label="Trending"   active={activeMenu === 'trending'}
                                onClick={() => toggle('trending')} />
                            <NavButton label="Tech Tips"  active={activeMenu === 'tech-tips'}
                                onClick={() => toggle('tech-tips')} emerald />
                            <NavButton label="News"       active={activeMenu === 'news'}
                                onClick={() => toggle('news')} rose />
                        </nav>

                        <div className="flex-1" />

                        {/* Desktop search */}
                        <form onSubmit={submitSearch} className="hidden md:block">
                            <div className="relative">
                                <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                                <input
                                    type="search"
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)}
                                    placeholder="Search posts, categories…"
                                    className="w-60 pl-9 pr-3 py-1.5 text-sm border border-gray-300 rounded-full
                                               focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400"
                                />
                            </div>
                        </form>

                        {/* Mobile: search icon + hamburger */}
                        <div className="flex md:hidden items-center gap-1">
                            <button
                                onClick={() => { setMobileOpen(true); setMobileSection('search'); }}
                                className="p-2 rounded-md text-gray-500 hover:text-indigo-600 hover:bg-gray-50"
                                aria-label="Search">
                                <SearchIcon className="w-5 h-5" />
                            </button>
                            <button
                                onClick={() => setMobileOpen(prev => !prev)}
                                className="p-2 rounded-md text-gray-500 hover:text-indigo-600 hover:bg-gray-50"
                                aria-label="Menu">
                                {mobileOpen
                                    ? <XIcon className="w-5 h-5" />
                                    : <HamburgerIcon className="w-5 h-5" />}
                            </button>
                        </div>
                    </div>
                </div>

                {/* ── Desktop megamenus ── */}
                <MegaMenu open={activeMenu === 'categories'}>
                    <CategoriesMegamenu categories={categories} onClose={() => setActiveMenu(null)} />
                </MegaMenu>
                <MegaMenu open={activeMenu === 'tags'}>
                    <TagsMegamenu tags={popularTags} onClose={() => setActiveMenu(null)} />
                </MegaMenu>
                <MegaMenu open={activeMenu === 'trending'}>
                    <TrendingMegamenu posts={trending} onClose={() => setActiveMenu(null)} />
                </MegaMenu>
                <MegaMenu open={activeMenu === 'tech-tips'} emerald>
                    <TechTipsMegamenu posts={latestTechTips} onClose={() => setActiveMenu(null)} />
                </MegaMenu>
                <MegaMenu open={activeMenu === 'news'} rose>
                    <NewsMegamenu posts={latestNews} onClose={() => setActiveMenu(null)} />
                </MegaMenu>

                {/* ── Mobile menu ── */}
                {mobileOpen && (
                    <MobileMenu
                        categories={categories}
                        tags={popularTags}
                        trending={trending}
                        techTips={latestTechTips}
                        latestNews={latestNews}
                        activeSection={mobileSection}
                        onToggleSection={name =>
                            setMobileSection(prev => prev === name ? null : name)}
                        onClose={() => setMobileOpen(false)}
                    />
                )}
            </header>

            <main className="flex-1">{children}</main>

            <footer className="bg-white border-t border-gray-200 py-8 text-xs text-gray-400">
                <div className="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p>
                        © {new Date().getFullYear()} GadgetDrop.tech ·
                        GadgetDrop participates in the Amazon Associates program.
                        We earn a small commission on qualifying purchases.
                    </p>
                    <nav className="flex items-center gap-4 shrink-0">
                        <Link href={route('about')}   className="hover:text-gray-600 transition-colors">About</Link>
                        <Link href={route('contact')} className="hover:text-gray-600 transition-colors">Contact</Link>
                        <Link href={route('privacy')} className="hover:text-gray-600 transition-colors">Privacy Policy</Link>
                        <Link href={route('cookies')} className="hover:text-gray-600 transition-colors">Cookie Policy</Link>
                        <Link href={route('terms')}   className="hover:text-gray-600 transition-colors">Terms</Link>
                    </nav>
                </div>
            </footer>
        </div>
    );
}

/* ─── Desktop nav button ─────────────────────────────────────── */
function NavButton({ label, active, onClick, emerald = false, rose = false }) {
    const activeClass = rose
        ? 'bg-rose-50 text-rose-700'
        : emerald ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700';
    const defaultClass = rose
        ? 'text-gray-600 hover:text-rose-600 hover:bg-rose-50'
        : emerald
            ? 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50'
            : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-50';
    return (
        <button
            onClick={onClick}
            className={`flex items-center gap-1 px-3 py-1.5 rounded-md text-sm font-medium transition-colors
                ${active ? activeClass : defaultClass}`}>
            {rose && (
                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                </svg>
            )}
            {emerald && !rose && (
                <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            )}
            {label}
            <ChevronIcon className={`w-3.5 h-3.5 transition-transform ${active ? 'rotate-180' : ''}`} />
        </button>
    );
}

/* ─── Megamenu wrapper ───────────────────────────────────────── */
function MegaMenu({ open, children, emerald = false, rose = false }) {
    if (!open) return null;
    const accent = rose
        ? 'bg-gradient-to-r from-rose-500 via-red-500 to-rose-400'
        : emerald
            ? 'bg-gradient-to-r from-emerald-400 via-teal-400 to-emerald-300'
            : 'bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-400';
    return (
        <div className="hidden md:block absolute top-full left-0 right-0 bg-white shadow-xl z-40">
            <div className={`h-0.5 ${accent}`} />
            {children}
        </div>
    );
}

/* ─── Categories megamenu ───────────────────────────────────── */
const AVATAR_COLORS = [
    ['bg-indigo-100 group-hover:bg-indigo-200', 'text-indigo-600'],
    ['bg-violet-100 group-hover:bg-violet-200', 'text-violet-600'],
    ['bg-sky-100 group-hover:bg-sky-200',       'text-sky-600'],
    ['bg-amber-100 group-hover:bg-amber-200',   'text-amber-600'],
    ['bg-rose-100 group-hover:bg-rose-200',     'text-rose-600'],
    ['bg-teal-100 group-hover:bg-teal-200',     'text-teal-600'],
];

function CategoriesMegamenu({ categories, onClose }) {
    if (categories.length === 0) return (
        <div className="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">No categories yet.</div>
    );
    return (
        <div className="max-w-6xl mx-auto px-4 py-6">
            <div className="flex items-center gap-2 mb-4">
                <span className="text-xs font-semibold text-gray-400 uppercase tracking-widest">Browse Categories</span>
                <span className="flex-1 h-px bg-gray-100" />
                <span className="text-xs text-gray-300">{categories.length} topics</span>
            </div>
            <div className="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                {categories.map((cat, i) => {
                    const [bg, text] = AVATAR_COLORS[i % AVATAR_COLORS.length];
                    return (
                        <Link key={cat.id} href={route('category', cat.slug)} onClick={onClose}
                            className="group flex flex-col items-center text-center p-3 rounded-xl hover:bg-gray-50 transition">
                            <div className={`w-10 h-10 rounded-full ${bg} flex items-center justify-center mb-2 transition`}>
                                <span className={`${text} font-bold text-sm`}>{cat.name.charAt(0)}</span>
                            </div>
                            <span className="text-xs font-medium text-gray-700 group-hover:text-gray-900 leading-tight">
                                {cat.name}
                            </span>
                            <span className="text-xs text-gray-400 mt-0.5">{cat.posts_count} posts</span>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

/* ─── Tags megamenu ─────────────────────────────────────────── */
function TagsMegamenu({ tags, onClose }) {
    if (tags.length === 0) return (
        <div className="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">No tags yet.</div>
    );

    const maxCount = Math.max(...tags.map(t => t.posts_count), 1);

    function tagStyle(count) {
        const ratio = count / maxCount;
        if (ratio > 0.65) return { size: 'text-sm font-semibold px-4 py-2', color: 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200' };
        if (ratio > 0.35) return { size: 'text-xs font-medium px-3 py-1.5', color: 'bg-gray-100 text-gray-700 hover:bg-indigo-100 hover:text-indigo-700' };
        return { size: 'text-xs px-2.5 py-1', color: 'bg-gray-50 text-gray-500 hover:bg-gray-100 hover:text-gray-700' };
    }

    return (
        <div className="max-w-6xl mx-auto px-4 py-6">
            <div className="flex items-center gap-2 mb-4">
                <span className="text-xs font-semibold text-gray-400 uppercase tracking-widest">Browse by Tag</span>
                <span className="flex-1 h-px bg-gray-100" />
                <span className="text-xs text-gray-300">sized by popularity</span>
            </div>
            <div className="flex flex-wrap gap-2 items-center">
                {tags.map(tag => {
                    const { size, color } = tagStyle(tag.posts_count);
                    return (
                        <Link key={tag.id}
                            href={route('search') + '?q=' + encodeURIComponent(tag.name)}
                            onClick={onClose}
                            className={`rounded-full transition ${size} ${color}`}>
                            {tag.name}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

/* ─── Trending megamenu ─────────────────────────────────────── */
function TrendingMegamenu({ posts, onClose }) {
    if (posts.length === 0) return (
        <div className="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">Nothing trending yet.</div>
    );
    return (
        <div className="max-w-6xl mx-auto px-4 py-6">
            <div className="flex items-center gap-2 mb-4">
                <span className="text-xs font-semibold text-gray-400 uppercase tracking-widest">Trending Now</span>
                <span className="flex-1 h-px bg-gray-100" />
            </div>
            <div className="grid grid-cols-2 lg:grid-cols-3 gap-3">
                {posts.map((p, i) => (
                    <Link key={p.id} href={route('posts.show', p.slug)} onClick={onClose}
                        className="flex gap-3 items-start group p-2 rounded-xl hover:bg-gray-50 transition -m-2">
                        <div className="relative flex-shrink-0">
                            {p.featured_image ? (
                                <img src={p.featured_image} alt={p.title}
                                    className="w-16 h-16 rounded-lg object-cover" />
                            ) : (
                                <div className="w-16 h-16 rounded-lg bg-indigo-50 flex items-center justify-center">
                                    <span className="text-indigo-300 font-bold text-xl">G</span>
                                </div>
                            )}
                            {/* Rank badge */}
                            <span className={`absolute -top-1.5 -left-1.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shadow-sm
                                ${i === 0
                                    ? 'bg-amber-400 text-white'
                                    : 'bg-white border border-gray-200 text-gray-500'}`}>
                                {i === 0 ? '★' : i + 1}
                            </span>
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-gray-800 group-hover:text-indigo-600
                                          leading-snug transition-colors line-clamp-2">
                                {p.title}
                            </p>
                            <p className="text-xs text-gray-400 mt-1">{p.published_at}</p>
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}

/* ─── Tech Tips megamenu ────────────────────────────────────── */
function TechTipsMegamenu({ posts, onClose }) {
    if (posts.length === 0) return (
        <div className="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">No Tech Tips published yet.</div>
    );
    return (
        <div className="bg-emerald-50/40">
            <div className="max-w-6xl mx-auto px-4 py-6">
                <div className="flex items-center gap-2 mb-4">
                    <svg className="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span className="text-xs font-semibold text-emerald-700 uppercase tracking-widest">Latest Tech Tips</span>
                    <span className="flex-1 h-px bg-emerald-100" />
                    <span className="text-xs text-emerald-400">{posts.length} tips</span>
                </div>
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    {posts.map((p, i) => (
                        <Link key={p.id} href={route('posts.show', p.slug)} onClick={onClose}
                            className="flex gap-3 items-start group p-2 rounded-xl hover:bg-emerald-100/60 transition -m-2">
                            <div className={`flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                                ${i === 0
                                    ? 'bg-emerald-500 shadow-sm'
                                    : 'bg-white border border-emerald-200'}`}>
                                <svg className={`w-4 h-4 ${i === 0 ? 'text-white' : 'text-emerald-500'}`}
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div className="min-w-0">
                                {i === 0 && (
                                    <span className="inline-block text-xs font-semibold text-emerald-600 mb-0.5">Latest</span>
                                )}
                                <p className="text-sm font-medium text-gray-800 group-hover:text-emerald-700
                                              leading-snug transition-colors line-clamp-2">
                                    {p.title}
                                </p>
                                <p className="text-xs text-gray-400 mt-1">{p.published_at}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ─── News megamenu ─────────────────────────────────────────── */
function NewsMegamenu({ posts, onClose }) {
    if (posts.length === 0) return (
        <div className="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">No news published yet.</div>
    );
    return (
        <div className="bg-rose-50/40">
            <div className="max-w-6xl mx-auto px-4 py-6">
                <div className="flex items-center gap-2 mb-4">
                    <svg className="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                    </svg>
                    <span className="text-xs font-semibold text-rose-700 uppercase tracking-widest">Latest Tech News</span>
                    <span className="flex-1 h-px bg-rose-100" />
                    <Link href={route('news')} onClick={onClose}
                        className="text-xs font-medium text-rose-500 hover:text-rose-700 transition-colors">
                        All news →
                    </Link>
                </div>
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    {posts.map((p, i) => (
                        <Link key={p.id} href={route('posts.show', p.slug)} onClick={onClose}
                            className="flex gap-3 items-start group p-2 rounded-xl hover:bg-rose-100/60 transition -m-2">
                            <div className={`flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                                ${i === 0
                                    ? 'bg-rose-500 shadow-sm'
                                    : 'bg-white border border-rose-200'}`}>
                                <svg className={`w-4 h-4 ${i === 0 ? 'text-white' : 'text-rose-400'}`}
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                                </svg>
                            </div>
                            <div className="min-w-0">
                                {i === 0 && (
                                    <span className="inline-block text-xs font-semibold text-rose-600 mb-0.5">Latest</span>
                                )}
                                <p className="text-sm font-medium text-gray-800 group-hover:text-rose-700
                                              leading-snug transition-colors line-clamp-2">
                                    {p.title}
                                </p>
                                <p className="text-xs text-gray-400 mt-1">{p.published_at}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ─── Mobile menu ───────────────────────────────────────────── */
function MobileMenu({ categories, tags, trending, techTips, latestNews = [], activeSection, onToggleSection, onClose }) {
    const [q, setQ] = useState('');

    function handleSearch(e) {
        e.preventDefault();
        const trimmed = q.trim();
        if (trimmed) {
            onClose();
            router.visit(route('search') + '?q=' + encodeURIComponent(trimmed));
        }
    }

    return (
        <div className="md:hidden border-t border-gray-100 bg-white max-h-[80vh] overflow-y-auto">
            {/* Search */}
            <div className="px-4 py-3 border-b border-gray-100">
                <form onSubmit={handleSearch} className="relative">
                    <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input
                        type="search"
                        value={q}
                        onChange={e => setQ(e.target.value)}
                        placeholder="Search posts, categories…"
                        autoFocus={activeSection === 'search'}
                        className="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-full
                                   focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    />
                </form>
            </div>

            <nav className="px-2 py-2">
                {/* Home */}
                <Link href={route('home')} onClick={onClose}
                    className="flex items-center px-3 py-3 text-sm font-medium text-gray-700
                               hover:text-indigo-600 hover:bg-gray-50 rounded-lg">
                    Home
                </Link>

                {/* Categories accordion */}
                <MobileAccordion
                    title="Categories"
                    open={activeSection === 'categories'}
                    onToggle={() => onToggleSection('categories')}>
                    <div className="grid grid-cols-2 gap-1 py-1">
                        {categories.map((cat, i) => {
                            const [, text] = AVATAR_COLORS[i % AVATAR_COLORS.length];
                            return (
                                <Link key={cat.id} href={route('category', cat.slug)} onClick={onClose}
                                    className="px-3 py-2 text-sm text-gray-700 hover:text-indigo-600
                                               hover:bg-indigo-50 rounded-lg flex items-center gap-2">
                                    <span className={`font-bold text-xs ${text}`}>{cat.name.charAt(0)}</span>
                                    {cat.name}
                                    <span className="text-xs text-gray-400 ml-auto">({cat.posts_count})</span>
                                </Link>
                            );
                        })}
                    </div>
                </MobileAccordion>

                {/* Tags accordion */}
                <MobileAccordion
                    title="Tags"
                    open={activeSection === 'tags'}
                    onToggle={() => onToggleSection('tags')}>
                    <div className="flex flex-wrap gap-1.5 py-2">
                        {tags.map(tag => (
                            <Link key={tag.id}
                                href={route('search') + '?q=' + encodeURIComponent(tag.name)}
                                onClick={onClose}
                                className="px-2.5 py-1 rounded-full bg-gray-100 hover:bg-indigo-100
                                           text-xs text-gray-700 hover:text-indigo-700 font-medium">
                                {tag.name}
                            </Link>
                        ))}
                    </div>
                </MobileAccordion>

                {/* Trending accordion */}
                <MobileAccordion
                    title="Trending"
                    open={activeSection === 'trending'}
                    onToggle={() => onToggleSection('trending')}>
                    <ul className="space-y-1 py-1">
                        {trending.map((p, i) => (
                            <li key={p.id}>
                                <Link href={route('posts.show', p.slug)} onClick={onClose}
                                    className="flex gap-3 items-center px-2 py-2 rounded-lg hover:bg-gray-50">
                                    <span className={`flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                                        ${i === 0 ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-500'}`}>
                                        {i === 0 ? '★' : i + 1}
                                    </span>
                                    {p.featured_image ? (
                                        <img src={p.featured_image} alt={p.title}
                                            className="w-10 h-10 rounded-md object-cover flex-shrink-0" />
                                    ) : (
                                        <div className="w-10 h-10 rounded-md bg-indigo-50 flex-shrink-0
                                                        flex items-center justify-center">
                                            <span className="text-indigo-300 font-bold">G</span>
                                        </div>
                                    )}
                                    <span className="text-sm text-gray-700 line-clamp-2">{p.title}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </MobileAccordion>

                {/* Tech Tips accordion */}
                {techTips.length > 0 && (
                    <MobileAccordion
                        title="Tech Tips"
                        open={activeSection === 'tech-tips'}
                        onToggle={() => onToggleSection('tech-tips')}
                        emerald>
                        <ul className="space-y-1 py-1">
                            {techTips.map((p, i) => (
                                <li key={p.id}>
                                    <Link href={route('posts.show', p.slug)} onClick={onClose}
                                        className="flex gap-3 items-center px-2 py-2 rounded-lg hover:bg-emerald-50">
                                        <div className={`w-8 h-8 rounded-md flex-shrink-0 flex items-center justify-center
                                            ${i === 0 ? 'bg-emerald-500' : 'bg-white border border-emerald-200'}`}>
                                            <svg className={`w-3.5 h-3.5 ${i === 0 ? 'text-white' : 'text-emerald-500'}`}
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        </div>
                                        <span className="text-sm text-gray-700 line-clamp-2">{p.title}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </MobileAccordion>
                )}

                {/* News accordion */}
                {latestNews.length > 0 && (
                    <MobileAccordion
                        title="News"
                        open={activeSection === 'news'}
                        onToggle={() => onToggleSection('news')}
                        rose>
                        <ul className="space-y-1 py-1">
                            {latestNews.map((p, i) => (
                                <li key={p.id}>
                                    <Link href={route('posts.show', p.slug)} onClick={onClose}
                                        className="flex gap-3 items-center px-2 py-2 rounded-lg hover:bg-rose-50">
                                        <div className={`w-8 h-8 rounded-md flex-shrink-0 flex items-center justify-center
                                            ${i === 0 ? 'bg-rose-500' : 'bg-white border border-rose-200'}`}>
                                            <svg className={`w-3.5 h-3.5 ${i === 0 ? 'text-white' : 'text-rose-400'}`}
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                                            </svg>
                                        </div>
                                        <span className="text-sm text-gray-700 line-clamp-2">{p.title}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        <Link href={route('news')} onClick={onClose}
                            className="block mt-2 text-xs font-medium text-rose-600 hover:text-rose-800 px-2 pb-2">
                            View all news →
                        </Link>
                    </MobileAccordion>
                )}
            </nav>
        </div>
    );
}

function MobileAccordion({ title, open, onToggle, children, emerald = false, rose = false }) {
    const openClass = rose
        ? 'text-rose-700 bg-rose-50'
        : emerald ? 'text-emerald-700 bg-emerald-50' : 'text-indigo-700 bg-indigo-50';
    const closedClass = rose
        ? 'text-gray-700 hover:text-rose-600 hover:bg-rose-50'
        : emerald ? 'text-gray-700 hover:text-emerald-600 hover:bg-emerald-50' : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50';

    return (
        <div className="border-t border-gray-50">
            <button onClick={onToggle}
                className={`flex items-center justify-between w-full px-3 py-3 text-sm font-medium rounded-lg transition-colors
                    ${open ? openClass : closedClass}`}>
                <span className="flex items-center gap-2">
                    {rose && (
                        <svg className="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                        </svg>
                    )}
                    {emerald && !rose && (
                        <svg className="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    )}
                    {title}
                </span>
                <ChevronIcon className={`w-4 h-4 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && <div className="px-3 pb-1">{children}</div>}
        </div>
    );
}

/* ─── SVG icon helpers ──────────────────────────────────────── */
function SearchIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24"
            stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round"
                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
        </svg>
    );
}
function ChevronIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24"
            stroke="currentColor" strokeWidth={2.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    );
}
function HamburgerIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24"
            stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    );
}
function XIcon({ className }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24"
            stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    );
}
