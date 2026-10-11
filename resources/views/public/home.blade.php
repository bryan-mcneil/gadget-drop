@extends('layouts.public')

@php
    $heroBg = '#030309';
    $darkBg = 'linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)';
    $spotlightBg = 'linear-gradient(160deg, #050510 0%, #0c0c1d 45%, #0e0b1f 100%)';

    // The hero carousel always spans the full width (the Drop Price game lives
    // in its own band below it), so the LCP image renders at ~100vw everywhere.
    $heroSizes = '100vw';

    $isWithin24h = function ($iso) {
        if (!$iso) return false;
        try { return \Illuminate\Support\Carbon::parse($iso)->gt(now()->subDay()); } catch (\Throwable $e) { return false; }
    };
    $fmtNewsDate = function ($d) {
        if (!$d) return '';
        try { return \Illuminate\Support\Carbon::parse($d)->format('M j, Y'); } catch (\Throwable $e) { return $d; }
    };
@endphp

@section('content')
    {{-- Preload the LCP hero image so the browser fetches it before CSS/JS parse.
         Match the WebP source the <picture> will pick (when variants exist) to
         avoid preloading a file the browser won't use. --}}
    @php $lcp = $heroSlides[0]['post'] ?? null; $lcpSrc = $lcp ? ($lcp['hero_image'] ?? $lcp['featured_image']) : null; @endphp
    @if($lcpSrc)
        @push('head')
            @php $lcpWebp = \App\Support\ResponsiveImage::webpSrcset($lcpSrc); @endphp
            @if($lcpWebp !== '')
                <link rel="preload" as="image" type="image/webp" imagesrcset="{{ $lcpWebp }}" imagesizes="{{ $heroSizes }}" fetchpriority="high">
            @else
                <link rel="preload" as="image" href="{{ $lcpSrc }}" fetchpriority="high">
            @endif
        @endpush
    @endif

    {{-- ── Hero carousel (full width) ── --}}
    @if(count($heroSlides) > 0)
        @php $count = count($heroSlides); @endphp
        <section id="hero" x-data="heroCarousel({{ $count }})" @mouseenter="paused = true" @mouseleave="paused = false"
            @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
            class="relative overflow-hidden min-h-[460px] md:min-h-[520px] text-white" style="background: {{ $heroBg }}">
            @foreach($heroSlides as $i => $slide)
                {{-- One <h1> per page: the first slide owns it; the rest are <h2>
                     (they're headings of visually-hidden slides). --}}
                @php $p = $slide['post']; $htag = $i === 0 ? 'h1' : 'h2'; @endphp
                <div class="absolute inset-0 transition-opacity duration-700"
                    style="{{ $i === 0 ? 'opacity:1;z-index:10' : 'opacity:0;z-index:0' }}"
                    :style="active === {{ $i }} ? 'opacity:1;z-index:10' : 'opacity:0;z-index:0'">
                    @if($p['featured_image'])
                        <x-responsive-image :src="$p['hero_image'] ?? $p['featured_image']" :alt="$p['title']"
                            :sizes="$heroSizes" width="1600" height="520" class="absolute inset-0 w-full h-full object-cover"
                            style="object-position: {{ $p['hero_image_position'] ?? $p['featured_image_position'] ?? 'center center' }}"
                            loading="{{ $i === 0 ? 'eager' : 'lazy' }}" fetchpriority="{{ $i === 0 ? 'high' : 'low' }}" />
                        <div class="absolute inset-0 bg-gradient-to-r from-gray-950/95 via-gray-950/75 to-gray-950/30"></div>
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br from-gray-950 to-indigo-950"></div>
                    @endif

                    <div class="relative z-10 h-full flex flex-col justify-center max-w-4xl mx-auto px-6 py-16">
                        @if($p['type'] === 'tech_news')
                            <span class="inline-flex items-center gap-1.5 text-rose-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                                <span class="relative flex h-2 w-2 mr-0.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                </span>
                                {{ $slide['label'] }}
                            </span>
                        @elseif($p['type'] === 'tech_tip')
                            <span class="inline-flex items-center gap-1.5 text-emerald-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                {{ $slide['label'] }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-indigo-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                                <span class="w-4 h-px bg-indigo-400"></span>
                                {{ $slide['label'] }}
                            </span>
                        @endif
                        <{{ $htag }} class="text-3xl md:text-4xl lg:text-5xl font-extrabold leading-tight max-w-2xl">{{ $p['title'] }}</{{ $htag }}>
                        @if(!empty($p['excerpt']))
                            <p class="mt-4 text-gray-300 text-base md:text-lg leading-relaxed max-w-xl line-clamp-2">{{ $p['excerpt'] }}</p>
                        @endif
                        <div class="mt-7 flex items-center gap-4">
                            <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate
                                class="inline-flex items-center gap-2 text-white font-semibold px-6 py-3 rounded-lg transition-colors {{ $p['type'] === 'tech_news' ? 'bg-rose-600 hover:bg-rose-500' : ($p['type'] === 'tech_tip' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-indigo-600 hover:bg-indigo-500') }}">
                                {{ $p['type'] === 'tech_news' ? 'Read the Story' : ($p['type'] === 'tech_tip' ? 'Read the Tip' : 'Read the Drop') }}
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </a>
                            @if(!empty($p['published_at']))
                                <span class="text-sm text-gray-400">{{ $p['published_at'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            @if($count > 1)
                {{-- Arrows are hidden on mobile/tablet (they overlapped the text); swipe instead. --}}
                <button @click="prev" aria-label="Previous slide" class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/10 hover:bg-white/25 backdrop-blur-sm hidden lg:flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button @click="next" aria-label="Next slide" class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/10 hover:bg-white/25 backdrop-blur-sm hidden lg:flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
                <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2">
                    @foreach($heroSlides as $i => $slide)
                        <button @click="go({{ $i }})" aria-label="Go to slide {{ $i + 1 }}" class="h-1.5 rounded-full transition-all duration-300" :class="active === {{ $i }} ? 'w-8 bg-indigo-400' : 'w-2 bg-white/35 hover:bg-white/60'"></button>
                    @endforeach
                </div>
                <div class="absolute top-4 right-4 z-20 text-xs text-white/50 tabular-nums"><span x-text="active + 1"></span> / {{ $count }}</div>
            @endif
        </section>
    @endif

    {{-- ── Categories strip ── --}}
    @if(count($categories) > 0)
        <section id="category" class="relative py-12" style="background: {{ $darkBg }}">
            <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, rgba(99,102,241,0.18) 1px, transparent 1px); background-size: 28px 28px;"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>
            <div class="relative max-w-[96rem] mx-auto px-4"
                x-data="categoryCarousel()"
                @mousemove.window="onMove($event)" @mouseup.window="onUp()" @resize.window="update()"
                @scroll.window.passive="onPageScroll()">
                <div class="flex items-end justify-between gap-4 mb-7">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-px bg-indigo-500"></span>
                        <h2 class="text-xs font-bold text-indigo-400 uppercase tracking-[0.2em]">Browse by Category</h2>
                    </div>
                    {{-- Prev/next arrows (pointer devices; mobile users swipe). Dim at the ends. --}}
                    <div class="hidden sm:flex items-center gap-2 transition-opacity duration-300"
                        x-cloak :class="scrollable ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                        <button type="button" @click="page(-1)" :disabled="!canLeft" aria-label="Scroll categories left"
                            class="w-9 h-9 rounded-full border border-white/10 bg-white/5 text-white flex items-center justify-center transition-all duration-200 hover:bg-white/15 hover:border-white/25 active:scale-95 disabled:opacity-25 disabled:cursor-not-allowed disabled:hover:bg-white/5 disabled:hover:border-white/10">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button type="button" @click="page(1)" :disabled="!canRight" aria-label="Scroll categories right"
                            class="w-9 h-9 rounded-full border border-white/10 bg-white/5 text-white flex items-center justify-center transition-all duration-200 hover:bg-white/15 hover:border-white/25 active:scale-95 disabled:opacity-25 disabled:cursor-not-allowed disabled:hover:bg-white/5 disabled:hover:border-white/10">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                </div>

                <div class="relative">
                    {{-- Edge fades hint there's more in either direction. --}}
                    <div class="pointer-events-none absolute inset-y-0 left-0 w-12 z-10 bg-gradient-to-r from-[#0d0d2b] to-transparent transition-opacity duration-300"
                        x-cloak :class="canLeft ? 'opacity-100' : 'opacity-0'"></div>
                    <div class="pointer-events-none absolute inset-y-0 right-0 w-12 z-10 bg-gradient-to-l from-[#0a0f1e] to-transparent transition-opacity duration-300"
                        x-cloak :class="canRight ? 'opacity-100' : 'opacity-0'"></div>

                    <div x-ref="track" @scroll.passive="update()" @wheel="onWheel($event)"
                        @mousedown="cardDown($event)" @click.capture="onClick($event)" @dragstart.prevent
                        class="flex gap-5 overflow-x-auto scrollbar-hide pb-1 cursor-grab select-none"
                        :class="cardDragging && 'cursor-grabbing'" style="touch-action: pan-x;">
                        @foreach($categories as $cat)
                            <a href="{{ route('category', $cat['slug']) }}" wire:navigate draggable="false" class="group relative flex-shrink-0 w-56 h-80 rounded-2xl overflow-hidden block shadow-lg shadow-black/40">
                                @if($cat['featured_image'])
                                    <x-responsive-image :src="$cat['featured_image']" :alt="$cat['name']" loading="lazy" width="224" height="320" sizes="224px" draggable="false" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                @else
                                    <div class="absolute inset-0 bg-gradient-to-br from-indigo-700 to-purple-900 flex items-center justify-center"><span class="text-white/20 font-black text-8xl select-none">{{ \Illuminate\Support\Str::substr($cat['name'], 0, 1) }}</span></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-gray-950/90 via-gray-950/20 to-transparent transition-opacity duration-300 group-hover:opacity-90"></div>
                                <div class="absolute bottom-0 left-0 right-0 px-4 py-4 translate-y-1 group-hover:translate-y-0 transition-transform duration-300">
                                    <p class="text-white font-bold text-base leading-tight tracking-wide">{{ $cat['name'] }}</p>
                                    <div class="mt-1.5 h-0.5 w-0 bg-indigo-400 rounded-full transition-all duration-300 group-hover:w-8"></div>
                                </div>
                                <div class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/5 group-hover:ring-indigo-500/50 transition-all duration-300"></div>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Custom progress bar: shows position + is click/drag seekable. --}}
                <div class="mt-5 flex items-center gap-4 transition-opacity duration-300"
                    x-cloak :class="scrollable ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                    <div x-ref="bar" @mousedown="barDown($event)" aria-hidden="true"
                        class="group relative flex-1 h-1.5 rounded-full bg-white/10 cursor-pointer">
                        <div class="absolute inset-y-0 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 shadow-[0_0_8px_rgba(99,102,241,0.5)] group-hover:from-indigo-400 group-hover:to-purple-400"
                            style="width: 100%; left: 0" :style="`width: ${thumbWidth}%; left: ${thumbLeft}%`"></div>
                    </div>
                    <span class="hidden sm:inline-flex items-center gap-1.5 text-[11px] font-medium text-indigo-300/40 uppercase tracking-wider whitespace-nowrap select-none">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l-3 3 3 3m8-6l3 3-3 3" /></svg>
                        Drag to explore
                    </span>
                </div>
            </div>
        </section>
    @endif

    {{-- ── Drop Price game band: full width, directly under the category strip.
         Display-only props — the secret answer is read server-side by the
         Livewire component, never passed here. ── --}}
    @if($dropPrice)
        <x-drop-price.band id="drop-price">
            <div class="relative max-w-6xl mx-auto px-4 py-10 md:py-14">
                @livewire('drop-price', ['number' => $dropPrice['number'], 'name' => $dropPrice['name'], 'image' => $dropPrice['image']])
            </div>
        </x-drop-price.band>
    @endif

    {{-- ── Top Picks ── --}}
    @if(count($topPicks) > 0)
        <section id="week-top-picks" class="relative bg-slate-50 py-16 overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent"></div>
            <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-indigo-100/70 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-purple-100/60 blur-3xl pointer-events-none"></div>
            <div class="relative max-w-6xl mx-auto px-4">
                <div class="flex items-center gap-3 mb-8">
                    <span class="w-6 h-px bg-indigo-500"></span>
                    <h2 class="text-xs font-bold text-indigo-600 uppercase tracking-[0.2em]">This Week's Top Picks</h2>
                    <span class="flex-1 h-px bg-gradient-to-r from-indigo-200 to-transparent"></span>
                </div>
                @php $feat = $topPicks[0]; $rest = array_slice($topPicks, 1); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Featured pick — spans two columns, a horizontal card with a
                         bigger image, the verdict chip, a one-line why, and the one
                         filled CTA. The rest are compact with a quiet text link, so
                         the row of identical buttons is broken. --}}
                    <div class="group relative col-span-2 flex flex-col sm:flex-row overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm hover:shadow-xl hover:-translate-y-0.5 motion-reduce:hover:translate-y-0 transition-all duration-300">
                        <span aria-hidden="true" class="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden bg-indigo-500/20">
                            <span class="block h-full w-full -translate-x-full group-hover:translate-x-0 transition-transform duration-500 ease-out motion-reduce:transition-none bg-gradient-to-r from-transparent via-indigo-500 to-indigo-400"></span>
                        </span>
                        <div class="relative bg-gray-50 flex items-center justify-center p-5 sm:w-2/5 min-h-[176px] overflow-hidden">
                            @if($feat['image_url'])
                                <x-responsive-image :src="$feat['image_url']" :alt="$feat['name']" loading="lazy" sizes="(min-width: 640px) 260px, 100vw" class="max-h-40 w-auto object-contain transition-transform duration-500 group-hover:scale-105 motion-reduce:group-hover:scale-100" />
                            @else
                                <div class="w-full h-full min-h-[140px] bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center"><span class="text-indigo-200 font-black text-6xl select-none">G</span></div>
                            @endif
                        </div>
                        <div class="flex flex-col flex-1 p-5 min-w-0">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.15em] text-indigo-600 mb-2">
                                <span class="w-4 h-px bg-indigo-500"></span>
                                Top Pick
                            </span>
                            <h3 class="text-base font-extrabold text-gray-900 leading-snug line-clamp-2 group-hover:text-indigo-600 transition-colors">{{ $feat['name'] }}</h3>
                            @if(!empty($feat['excerpt']))
                                <p class="mt-2 text-sm text-gray-500 leading-relaxed line-clamp-2">{{ \Illuminate\Support\Str::limit($feat['excerpt'], 120) }}</p>
                            @endif
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @if($feat['price'])
                                    <span class="text-lg font-black text-gray-900 tabular-nums">${{ number_format((float) $feat['price'], 2) }}</span>
                                @endif
                                @if(!empty($feat['verdict']))
                                    <x-verdict-badge :verdict="$feat['verdict']" size="sm" />
                                @endif
                            </div>
                            <div class="mt-auto pt-4">
                                @if(!empty($feat['post_slug']))
                                    <a href="{{ route('posts.show', $feat['post_slug']) }}" wire:navigate
                                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-sm">
                                        Read review
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                    </a>
                                @else
                                    <a href="{{ route('affiliate.redirect', $feat['id']) }}" target="_blank" rel="nofollow noopener"
                                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold text-gray-900 transition-colors shadow-sm"
                                        style="background: linear-gradient(135deg, #FFB84D 0%, #FF9900 100%)">
                                        View on Amazon
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Compact picks --}}
                    @foreach($rest as $product)
                        <div class="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm hover:shadow-lg hover:-translate-y-0.5 motion-reduce:hover:translate-y-0 transition-all duration-300">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden bg-indigo-500/20">
                                <span class="block h-full w-full -translate-x-full group-hover:translate-x-0 transition-transform duration-500 ease-out motion-reduce:transition-none bg-gradient-to-r from-transparent via-indigo-500 to-indigo-400"></span>
                            </span>
                            <div class="relative bg-gray-50 flex items-center justify-center h-32 overflow-hidden">
                                @if($product['image_url'])
                                    <x-responsive-image :src="$product['image_url']" :alt="$product['name']" loading="lazy" sizes="180px" class="max-h-24 w-auto object-contain p-3 transition-transform duration-500 group-hover:scale-105 motion-reduce:group-hover:scale-100" />
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-indigo-50 to-purple-50 flex items-center justify-center"><span class="text-indigo-200 font-black text-5xl select-none">G</span></div>
                                @endif
                            </div>
                            <div class="p-4 flex flex-col flex-1 min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 leading-snug line-clamp-2 flex-1 group-hover:text-indigo-600 transition-colors">{{ $product['name'] }}</h3>
                                @if($product['price'])
                                    <span class="mt-2 text-sm font-extrabold text-gray-900 tabular-nums">${{ number_format((float) $product['price'], 2) }}</span>
                                @endif
                                @if(!empty($product['post_slug']))
                                    <a href="{{ route('posts.show', $product['post_slug']) }}" wire:navigate
                                        class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors">
                                        Read review
                                        <svg class="w-3 h-3 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                    </a>
                                @else
                                    <a href="{{ route('affiliate.redirect', $product['id']) }}" target="_blank" rel="nofollow noopener"
                                        class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-amber-600 hover:text-amber-700 transition-colors">
                                        View on Amazon
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── Featured Spotlight ── --}}
    @if($spotlight)
        @php $sp = $spotlight['product']; $spPost = $spotlight['post']; @endphp
        <section id="editor-pick" class="relative py-16 overflow-hidden" style="background: {{ $spotlightBg }}">
            <div class="absolute left-0 top-1/2 -translate-y-1/2 w-[480px] h-[480px] rounded-full bg-indigo-700/15 blur-[100px] pointer-events-none"></div>
            <div class="absolute right-0 bottom-0 w-72 h-72 rounded-full bg-purple-700/10 blur-[80px] pointer-events-none"></div>
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/25 to-transparent"></div>
            <div class="relative max-w-6xl mx-auto px-4">
                <div class="flex items-center gap-3 mb-10">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-400"></span>
                    </span>
                    <span class="text-xs font-bold text-indigo-400 uppercase tracking-[0.2em]">Editor's Pick</span>
                    <span class="flex-1 h-px bg-gradient-to-r from-indigo-500/40 to-transparent"></span>
                </div>
                <div class="grid md:grid-cols-5 gap-10 items-center">
                    <div class="md:col-span-2 relative min-w-0">
                        <div class="absolute inset-0 rounded-3xl bg-indigo-600/20 blur-2xl scale-90 pointer-events-none"></div>
                        <div class="relative rounded-3xl overflow-hidden border border-white/5 bg-white/[0.04] backdrop-blur-sm p-8 flex items-center justify-center min-h-[260px]">
                            @if($sp['image_url'])
                                <x-responsive-image :src="$sp['image_url']" :alt="$sp['name']" loading="lazy" sizes="(min-width: 768px) 40vw, 90vw" class="max-h-56 max-w-full w-auto object-contain drop-shadow-2xl" />
                            @else
                                <span class="text-white/10 font-black text-[8rem] leading-none select-none">G</span>
                            @endif
                        </div>
                    </div>
                    <div class="md:col-span-3 space-y-5 min-w-0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest truncate">Featured in: {{ $spPost['title'] }}</p>
                        <h2 class="text-3xl lg:text-4xl font-extrabold text-white leading-tight">{{ $sp['name'] }}</h2>
                        @if(!empty($sp['description']))
                            <p class="text-gray-400 leading-relaxed line-clamp-3 text-base">{{ $sp['description'] }}</p>
                        @endif
                        @if($sp['price'])
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black text-white">${{ number_format((float) $sp['price'], 2) }}</span>
                                <span class="text-sm text-gray-500">on Amazon</span>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <a href="{{ route('posts.show', $spPost['slug']) }}" wire:navigate class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm border border-white/10">
                                Read the Drop
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </a>
                            <a href="{{ route('affiliate.redirect', $sp['id']) }}" target="_blank" rel="nofollow noopener"
                                class="inline-flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-200 text-sm text-gray-900 shadow-lg shadow-amber-500/20 hover:shadow-amber-400/40 hover:scale-[1.02]"
                                style="background: linear-gradient(135deg, #FFB84D 0%, #FF9900 100%)">
                                View on Amazon
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                        </div>
                        <p class="text-xs text-gray-600 pt-1">#ad #commissionsearned. As an Amazon Associate we earn from qualifying purchases.</p>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ── Recent Drops ── --}}
    <div id="recent-drops" class="relative bg-white">
        <div class="h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent"></div>
        <div class="max-w-6xl mx-auto px-4 py-16 grid grid-cols-1 lg:grid-cols-4 gap-10">
            <main class="lg:col-span-3">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-3">
                        <span class="w-1 h-7 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
                        <div>
                            <h2 class="text-2xl font-extrabold text-gray-900 leading-none">Recent Drops</h2>
                            <p class="text-xs text-gray-500 mt-0.5 tracking-wide">The latest from GadgetDrop</p>
                        </div>
                    </div>
                    <a href="{{ route('search') }}" wire:navigate class="text-sm text-indigo-600 hover:text-indigo-700 font-semibold flex items-center gap-1 transition-colors">
                        View all
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    @foreach($recentPosts as $post)
                        <x-post-card :post="$post" />
                    @endforeach
                </div>
            </main>

            <aside class="space-y-5">
                <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                    <h3 class="flex items-center gap-2 font-bold text-gray-800 text-xs uppercase tracking-widest mb-4">
                        <span class="w-3 h-px bg-indigo-400"></span>
                        Categories
                    </h3>
                    <ul class="space-y-1">
                        @foreach($categories as $cat)
                            <li>
                                <a href="{{ route('category', $cat['slug']) }}" wire:navigate class="flex items-center justify-between group py-1 text-sm text-gray-700 hover:text-indigo-600 transition-colors">
                                    <span>{{ $cat['name'] }}</span>
                                    <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if(count($popularTags) > 0)
                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                        <h3 class="flex items-center gap-2 font-bold text-gray-800 text-xs uppercase tracking-widest mb-4">
                            <span class="w-3 h-px bg-indigo-400"></span>
                            Popular Tags
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach(collect($popularTags)->take(10) as $tag)
                                <a href="{{ route('tag', $tag['slug']) }}" wire:navigate class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full font-medium transition-colors">#{{ $tag['name'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>

    {{-- ── Breaking News ── --}}
    @if(count($latestNews) > 0)
        @php $bn = $latestNews[0]; $bnBreaking = $isWithin24h($bn['published_at_iso']); $bnImg = $bn['hero_image'] ?? $bn['featured_image']; @endphp
        <section id="news" class="relative overflow-hidden" style="background: {{ $darkBg }}">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rose-500 via-red-400 to-rose-600 z-10"></div>
            <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, rgba(99,102,241,0.12) 1px, transparent 1px); background-size: 28px 28px;"></div>
            <div class="absolute -bottom-24 -right-24 w-80 h-80 rounded-full bg-rose-700/10 blur-3xl pointer-events-none"></div>
            <div class="absolute top-0 left-1/3 w-64 h-64 rounded-full bg-indigo-700/10 blur-[80px] pointer-events-none"></div>

            @if($bnImg)
                <div class="pt-1">
                    <x-responsive-image :src="$bnImg" :alt="$bn['title']" loading="lazy" sizes="100vw" class="w-full object-cover"
                        style="height: 460px; object-position: {{ $bn['hero_image_position'] ?? $bn['featured_image_position'] ?? 'center center' }}; mask-image: linear-gradient(to bottom, black 0%, black 20%, transparent 92%); -webkit-mask-image: linear-gradient(to bottom, black 0%, black 20%, transparent 92%);" />
                </div>
            @endif

            <div class="relative z-10 max-w-6xl mx-auto px-4 pb-14 {{ $bnImg ? '-mt-44' : 'pt-16' }}">
                <div class="flex items-center gap-3 mb-4">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                    </span>
                    <span class="text-rose-400 text-xs font-black uppercase tracking-[0.25em]">{{ $bnBreaking ? 'Breaking News' : 'Latest Tech News' }}</span>
                    <span class="h-px flex-1 max-w-16 bg-gradient-to-r from-rose-500/40 to-transparent"></span>
                </div>
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-extrabold text-white leading-tight max-w-3xl mb-4">{{ $bn['title'] }}</h2>
                @if(!empty($bn['excerpt']))
                    <p class="text-indigo-200/60 text-base md:text-lg leading-relaxed max-w-2xl line-clamp-2 mb-5">{{ $bn['excerpt'] }}</p>
                @endif
                <div class="flex items-center gap-3 text-xs text-indigo-400/60 mb-8"><span>{{ $fmtNewsDate($bn['published_at']) }}</span></div>
                <a href="{{ route('posts.show', $bn['slug']) }}" wire:navigate class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-500 text-white font-bold px-7 py-3 rounded-xl transition-colors shadow-lg shadow-rose-950/50">
                    Read the Story
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>
        </section>
    @endif

    {{-- ── Tools ── --}}
    @if(count($tools) > 0)
        @php
            $toolsMap = collect($tools)->keyBy('slug');
            $featuredSlugs = ['image-editor', 'background-remover', 'color-palette', 'meta-tag-previewer'];
            $featuredTools = collect($featuredSlugs)->map(fn ($s) => $toolsMap->get($s))->filter()->values()->all();
            $featured = $featuredTools[0] ?? $tools[0];
            $rest = array_slice($featuredTools, 1);
        @endphp
        <section id="tools" class="bg-slate-50 py-16 relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-200 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-amber-200 to-transparent"></div>
            <div class="max-w-6xl mx-auto px-4">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-3">
                        <span class="w-1 h-7 rounded-full bg-gradient-to-b from-amber-400 to-orange-400"></span>
                        <div>
                            <h2 class="text-2xl font-extrabold text-gray-900 leading-none">Free Online Tools</h2>
                            <p class="text-xs text-gray-500 mt-0.5 tracking-wide">Runs in your browser, nothing sent to a server</p>
                        </div>
                    </div>
                    <a href="{{ route('tools.index') }}" class="text-sm text-amber-600 hover:text-amber-700 font-semibold flex items-center gap-1 transition-colors">
                        All tools
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    {{-- Featured tool --}}
                    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-300">
                        <div class="p-7 flex flex-col h-full">
                            <div class="flex items-center gap-2 mb-5">
                                <span class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" /></svg>
                                    Featured Tool
                                </span>
                            </div>
                            <div class="flex items-start gap-4 mb-4">
                                <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex-shrink-0 flex items-center justify-center p-2.5 text-amber-600">
                                    <x-tool-icon :icon="$featured['icon'] ?? 'wrench'" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-extrabold text-gray-900">{{ $featured['name'] }}</h3>
                                    <p class="text-sm text-gray-500 mt-0.5 leading-relaxed">{{ $featured['description'] }}</p>
                                </div>
                            </div>
                            <div class="flex-1 mb-6">
                                <x-tools.preview :slug="$featured['slug']" />
                            </div>
                            <a href="{{ route('tools.' . $featured['slug']) }}" class="inline-flex items-center gap-2 self-start bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm shadow-sm">
                                Use Tool Free
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        </div>
                    </div>
                    {{-- Aside --}}
                    <div class="flex flex-col gap-4">
                        @foreach($rest as $tool)
                            <a href="{{ route('tools.' . $tool['slug']) }}" class="group flex gap-4 items-start bg-white border border-gray-200 rounded-2xl p-5 hover:border-amber-300 hover:shadow-md transition-all duration-200 shadow-sm">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-100 flex-shrink-0 flex items-center justify-center p-2 text-amber-600 group-hover:bg-amber-100 transition-colors">
                                    <x-tool-icon :icon="$tool['icon'] ?? 'wrench'" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-gray-900 group-hover:text-amber-700 transition-colors text-sm">{{ $tool['name'] }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5 leading-relaxed line-clamp-2">{{ $tool['description'] }}</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-amber-500 flex-shrink-0 mt-0.5 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </a>
                        @endforeach
                        <a href="{{ route('tools.index') }}" class="group flex gap-4 items-center bg-amber-50 border border-amber-200 border-dashed rounded-2xl p-5 hover:bg-amber-100 hover:border-amber-300 transition-all duration-200">
                            <div class="w-10 h-10 rounded-xl bg-white border border-amber-200 flex-shrink-0 flex items-center justify-center text-amber-500 group-hover:bg-amber-50 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-amber-700 text-sm">More tools coming</p>
                                <p class="text-xs text-amber-600/70 mt-0.5">Markdown editor, diff checker, and more</p>
                            </div>
                            <svg class="w-4 h-4 text-amber-400 group-hover:text-amber-600 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ── Join the Drop ── --}}
    @livewire('join-the-drop')
@endsection
