@props(['navigation' => [], 'dealsCount' => 0])

@php
    $trending       = $navigation['trending'] ?? [];
    $latestTechTips = $navigation['latestTechTips'] ?? [];
    $latestNews     = $navigation['latestNews'] ?? [];
    // Tools were de-emphasised for the AdSense review (de-indexed, footer-only).
    // "Guides" surfaces only once a Guides category exists so the link never 404s.
    $guides         = collect($navigation['categories'] ?? [])->firstWhere('slug', 'guides');
    // Active-section highlight uses the header's existing active-pill idiom
    // (tinted bg + text), the same treatment the dropdown buttons already use.
    $onDeals        = request()->routeIs('deals');
@endphp

<header x-data="siteHeader"
    @keydown.escape.window="closeAll()"
    @click.outside="activeMenu = null"
    class="bg-white border-b border-gray-200 sticky top-0 z-50">

    {{-- ── Main bar ── --}}
    <div class="max-w-6xl mx-auto px-4">
        <div class="h-16 flex items-center gap-4">

            {{-- Logo --}}
            <a href="{{ route('home') }}" wire:navigate class="font-extrabold text-xl text-gray-900 tracking-tight shrink-0 mr-2">
                Gadget<span class="text-indigo-600">Drop</span>
            </a>

            {{-- Desktop nav items --}}
            <nav class="hidden md:flex items-center gap-1">
                {{-- Trending --}}
                <button @click="toggle('trending')"
                    class="flex items-center gap-1 px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
                    :class="activeMenu === 'trending' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:text-indigo-600 hover:bg-indigo-50'">
                    Trending
                    <svg class="w-3.5 h-3.5 transition-transform" :class="activeMenu === 'trending' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                {{-- Tech Tips --}}
                <button @click="toggle('tech-tips')"
                    class="flex items-center gap-1 px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
                    :class="activeMenu === 'tech-tips' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:text-emerald-600 hover:bg-emerald-50'">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Tech Tips
                    <svg class="w-3.5 h-3.5 transition-transform" :class="activeMenu === 'tech-tips' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                {{-- News --}}
                <button @click="toggle('news')"
                    class="flex items-center gap-1 px-3 py-1.5 rounded-md text-sm font-medium transition-colors"
                    :class="activeMenu === 'news' ? 'bg-rose-50 text-rose-700' : 'text-gray-600 hover:text-rose-600 hover:bg-rose-50'">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                    News
                    <svg class="w-3.5 h-3.5 transition-transform" :class="activeMenu === 'news' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                {{-- Guides (shown once a "Guides" category exists) --}}
                @if($guides)
                    <a href="{{ route('category', 'guides') }}" wire:navigate
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        Guides
                    </a>
                @endif
                {{-- Deals — tracked price drops (live count pill; active-section tint) --}}
                <a href="{{ route('deals') }}" wire:navigate
                    @class([
                        'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors',
                        'text-sky-700 bg-sky-50' => $onDeals,
                        'text-gray-600 hover:text-sky-600 hover:bg-sky-50' => ! $onDeals,
                    ])>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181" /></svg>
                    Deals
                    @if($dealsCount > 0)
                        <span class="ml-0.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-sky-100 text-sky-700 text-[11px] font-bold tabular-nums">{{ $dealsCount }}</span>
                    @endif
                </a>
            </nav>

            <div class="flex-1"></div>

            {{-- Desktop search --}}
            <form action="{{ route('search') }}" method="GET" class="hidden md:block">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                    <input type="search" name="q" placeholder="Search posts, categories…"
                        class="w-44 lg:w-60 pl-9 pr-3 py-1.5 text-sm border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400" />
                </div>
            </form>

            {{-- Mobile: search icon + hamburger --}}
            <div class="flex md:hidden items-center gap-1">
                <button @click="openMobileSearch()" class="p-2 rounded-md text-gray-500 hover:text-indigo-600 hover:bg-gray-50" aria-label="Search">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                </button>
                <button @click="mobileOpen = !mobileOpen" class="p-2 rounded-md text-gray-500 hover:text-indigo-600 hover:bg-gray-50" aria-label="Menu">
                    <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Desktop megamenus ── --}}
    {{-- Trending --}}
    <div x-show="activeMenu === 'trending'" x-cloak class="hidden md:block absolute top-full left-0 right-0 bg-white shadow-xl z-40">
        <div class="h-0.5 bg-gradient-to-r from-indigo-500 via-violet-500 to-indigo-400"></div>
        @if(count($trending) === 0)
            <div class="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">Nothing trending yet.</div>
        @else
        <div class="bg-indigo-50/70"><div class="max-w-6xl mx-auto px-4 py-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="text-xs font-semibold text-indigo-700 uppercase tracking-widest">Trending Now</span>
                <span class="flex-1 h-px bg-indigo-100"></span>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($trending as $i => $p)
                    <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate @click="activeMenu = null"
                        class="flex gap-3 items-start group p-2 rounded-xl hover:bg-indigo-100/60 transition -m-2">
                        <div class="relative flex-shrink-0">
                            @if($p['featured_image'])
                                <x-responsive-image :src="$p['featured_image']" :alt="$p['title']" sizes="64px" loading="lazy" width="64" height="64" class="w-16 h-16 rounded-lg object-cover" />
                            @else
                                <div class="w-16 h-16 rounded-lg bg-indigo-50 flex items-center justify-center"><span class="text-indigo-300 font-bold text-xl">G</span></div>
                            @endif
                            <span class="absolute -top-1.5 -left-1.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shadow-sm {{ $i === 0 ? 'bg-amber-400 text-white' : 'bg-white border border-gray-200 text-gray-500' }}">{{ $i === 0 ? '★' : $i + 1 }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-indigo-600 leading-snug transition-colors line-clamp-2">{{ $p['title'] }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $p['published_at'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div></div>
        @endif
    </div>

    {{-- Tech Tips --}}
    <div x-show="activeMenu === 'tech-tips'" x-cloak class="hidden md:block absolute top-full left-0 right-0 bg-white shadow-xl z-40">
        <div class="h-0.5 bg-gradient-to-r from-emerald-400 via-teal-400 to-emerald-300"></div>
        @if(count($latestTechTips) === 0)
            <div class="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">No Tech Tips published yet.</div>
        @else
        <div class="bg-emerald-50/40"><div class="max-w-6xl mx-auto px-4 py-6">
            <div class="flex items-center gap-2 mb-6">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                <span class="text-xs font-semibold text-emerald-700 uppercase tracking-widest">Latest Tech Tips</span>
                <span class="flex-1 h-px bg-emerald-100"></span>
                <span class="text-xs text-emerald-400">{{ count($latestTechTips) }} tips</span>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($latestTechTips as $i => $p)
                    <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate @click="activeMenu = null"
                        class="flex gap-3 items-start group p-2 rounded-xl hover:bg-emerald-100/60 transition -m-2">
                        <div class="relative flex-shrink-0">
                            @if($i === 0)<span class="absolute bottom-full inset-x-0 text-center text-xs font-semibold text-emerald-600 pb-0.5">Latest</span>@endif
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $i === 0 ? 'bg-emerald-500 shadow-sm' : 'bg-white border border-emerald-200' }}">
                                <svg class="w-4 h-4 {{ $i === 0 ? 'text-white' : 'text-emerald-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-emerald-700 leading-snug transition-colors line-clamp-2">{{ $p['title'] }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $p['published_at'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div></div>
        @endif
    </div>

    {{-- News --}}
    <div x-show="activeMenu === 'news'" x-cloak class="hidden md:block absolute top-full left-0 right-0 bg-white shadow-xl z-40">
        <div class="h-0.5 bg-gradient-to-r from-rose-500 via-red-500 to-rose-400"></div>
        @if(count($latestNews) === 0)
            <div class="max-w-6xl mx-auto px-4 py-6 text-sm text-gray-400">No news published yet.</div>
        @else
        <div class="bg-rose-50/40"><div class="max-w-6xl mx-auto px-4 py-6">
            <div class="flex items-center gap-2 mb-6">
                <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                <span class="text-xs font-semibold text-rose-700 uppercase tracking-widest">Latest Tech News</span>
                <span class="flex-1 h-px bg-rose-100"></span>
                <a href="{{ route('news') }}" wire:navigate @click="activeMenu = null" class="text-xs font-medium text-rose-500 hover:text-rose-700 transition-colors">All news →</a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($latestNews as $i => $p)
                    <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate @click="activeMenu = null"
                        class="flex gap-3 items-start group p-2 rounded-xl hover:bg-rose-100/60 transition -m-2">
                        <div class="relative flex-shrink-0">
                            @if($i === 0)<span class="absolute bottom-full inset-x-0 text-center text-xs font-semibold text-rose-600 pb-0.5">Latest</span>@endif
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $i === 0 ? 'bg-rose-500 shadow-sm' : 'bg-white border border-rose-200' }}">
                                <svg class="w-4 h-4 {{ $i === 0 ? 'text-white' : 'text-rose-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-rose-700 leading-snug transition-colors line-clamp-2">{{ $p['title'] }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $p['published_at'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div></div>
        @endif
    </div>

    {{-- Tools megamenu removed during the AdSense review (tools de-indexed; footer link retained). --}}

    {{-- ── Mobile menu ── --}}
    <div x-show="mobileOpen" x-cloak class="md:hidden border-t border-gray-100 bg-white max-h-[80vh] overflow-y-auto">
        {{-- Search --}}
        <div class="px-4 py-3 border-b border-gray-100">
            <form action="{{ route('search') }}" method="GET" class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                <input type="search" name="q" placeholder="Search posts, categories…" x-ref="mobileSearchInput"
                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            </form>
        </div>

        <nav class="px-2 py-2">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center px-3 py-3 text-sm font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50 rounded-lg">Home</a>
            @if($guides)
            <a href="{{ route('category', 'guides') }}" wire:navigate class="flex items-center gap-2 px-3 py-3 text-sm font-medium text-gray-700 hover:text-indigo-600 hover:bg-gray-50 rounded-lg">
                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                Guides
            </a>
            @endif
            <a href="{{ route('deals') }}" wire:navigate
                @class([
                    'flex items-center gap-2 px-3 py-3 text-sm font-medium rounded-lg',
                    'text-sky-700 bg-sky-50' => $onDeals,
                    'text-gray-700 hover:text-sky-600 hover:bg-gray-50' => ! $onDeals,
                ])>
                <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.286-4.286a11.948 11.948 0 014.306 6.43l.776 2.898m0 0l3.182-5.511m-3.182 5.51l-5.511-3.181" /></svg>
                Deals
                @if($dealsCount > 0)
                    <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-sky-100 text-sky-700 text-[11px] font-bold tabular-nums">{{ $dealsCount }}</span>
                @endif
            </a>

            {{-- Trending accordion --}}
            <div class="border-t border-gray-50">
                <button @click="toggleMobileSection('trending')"
                    class="flex items-center justify-between w-full px-3 py-3 text-sm font-medium rounded-lg transition-colors"
                    :class="mobileSection === 'trending' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-700 hover:text-indigo-600 hover:bg-indigo-50'">
                    <span class="flex items-center gap-2">Trending</span>
                    <svg class="w-4 h-4 transition-transform" :class="mobileSection === 'trending' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="mobileSection === 'trending'" x-cloak class="px-3 pb-1">
                    <ul class="space-y-1 py-1">
                        @foreach($trending as $i => $p)
                            <li>
                                <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate class="flex gap-3 items-center px-2 py-2 rounded-lg hover:bg-indigo-50">
                                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold {{ $i === 0 ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-500' }}">{{ $i === 0 ? '★' : $i + 1 }}</span>
                                    @if($p['featured_image'])
                                        <x-responsive-image :src="$p['featured_image']" :alt="$p['title']" sizes="40px" loading="lazy" width="40" height="40" class="w-10 h-10 rounded-md object-cover flex-shrink-0" />
                                    @else
                                        <div class="w-10 h-10 rounded-md bg-indigo-50 flex-shrink-0 flex items-center justify-center"><span class="text-indigo-300 font-bold">G</span></div>
                                    @endif
                                    <span class="text-sm text-gray-700 line-clamp-2">{{ $p['title'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Tech Tips accordion --}}
            @if(count($latestTechTips) > 0)
            <div class="border-t border-gray-50">
                <button @click="toggleMobileSection('tech-tips')"
                    class="flex items-center justify-between w-full px-3 py-3 text-sm font-medium rounded-lg transition-colors"
                    :class="mobileSection === 'tech-tips' ? 'text-emerald-700 bg-emerald-50' : 'text-gray-700 hover:text-emerald-600 hover:bg-emerald-50'">
                    <span class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        Tech Tips
                    </span>
                    <svg class="w-4 h-4 transition-transform" :class="mobileSection === 'tech-tips' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="mobileSection === 'tech-tips'" x-cloak class="px-3 pb-1">
                    <ul class="space-y-1 py-1">
                        @foreach($latestTechTips as $i => $p)
                            <li>
                                <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate class="flex gap-3 items-center px-2 py-2 rounded-lg hover:bg-emerald-50">
                                    <div class="w-8 h-8 rounded-md flex-shrink-0 flex items-center justify-center {{ $i === 0 ? 'bg-emerald-500' : 'bg-white border border-emerald-200' }}">
                                        <svg class="w-3.5 h-3.5 {{ $i === 0 ? 'text-white' : 'text-emerald-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                    </div>
                                    <span class="text-sm text-gray-700 line-clamp-2">{{ $p['title'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            {{-- News accordion --}}
            @if(count($latestNews) > 0)
            <div class="border-t border-gray-50">
                <button @click="toggleMobileSection('news')"
                    class="flex items-center justify-between w-full px-3 py-3 text-sm font-medium rounded-lg transition-colors"
                    :class="mobileSection === 'news' ? 'text-rose-700 bg-rose-50' : 'text-gray-700 hover:text-rose-600 hover:bg-rose-50'">
                    <span class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                        News
                    </span>
                    <svg class="w-4 h-4 transition-transform" :class="mobileSection === 'news' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="mobileSection === 'news'" x-cloak class="px-3 pb-1">
                    <ul class="space-y-1 py-1">
                        @foreach($latestNews as $i => $p)
                            <li>
                                <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate class="flex gap-3 items-center px-2 py-2 rounded-lg hover:bg-rose-50">
                                    <div class="w-8 h-8 rounded-md flex-shrink-0 flex items-center justify-center {{ $i === 0 ? 'bg-rose-500' : 'bg-white border border-rose-200' }}">
                                        <svg class="w-3.5 h-3.5 {{ $i === 0 ? 'text-white' : 'text-rose-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                                    </div>
                                    <span class="text-sm text-gray-700 line-clamp-2">{{ $p['title'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('news') }}" wire:navigate class="block mt-2 text-xs font-medium text-rose-600 hover:text-rose-800 px-2 pb-2">View all news →</a>
                </div>
            </div>
            @endif

            {{-- Tools accordion removed during the AdSense review (tools de-indexed; footer link retained). --}}
        </nav>
    </div>
</header>
