@extends('layouts.public')

@php
$sectionColor = $post['type'] === 'tech_news' ? 'rose' : ($post['type'] === 'tech_tip' ? 'emerald' : 'indigo');

$isBreaking = false;
if (!empty($post['published_at_iso'])) {
try { $isBreaking = \Illuminate\Support\Carbon::parse($post['published_at_iso'])->gt(now()->subDay()); } catch (\Throwable $e) {}
}

$sourceDomain = $post['source_url'] ?? null;
if ($sourceDomain) {
$host = parse_url($post['source_url'], PHP_URL_HOST);
if ($host) { $sourceDomain = preg_replace('/^www\./', '', $host); }
}

// Type label + accent classes for the dark-band eyebrow. Full literal class
// strings (no interpolation) so Tailwind's purge scanner keeps them.
$typeLabel = $post['type'] === 'tech_news' ? 'Tech News' : ($post['type'] === 'tech_tip' ? 'Tech Tip' : 'Review');
$eyebrowText = ['indigo' => 'text-indigo-400', 'emerald' => 'text-emerald-400', 'rose' => 'text-rose-400'][$sectionColor];
$eyebrowBar  = ['indigo' => 'bg-indigo-400',   'emerald' => 'bg-emerald-400',   'rose' => 'bg-rose-400'][$sectionColor];

$firstCategory = collect($post['categories'])->first();

// The first product with a tracked price feeds the one-line band intel strip.
$intelStats = null;
foreach ($post['products'] as $p) {
    if (!empty($p['price_intel']) && ($p['price_intel']['current'] ?? null) !== null) {
        $intelStats = $p['price_intel'];
        break;
    }
}

// One trimmed sidebar post list (capped at 5), most-relevant per post type.
$sidebarList = $post['type'] === 'tech_news'
    ? ['title' => 'More News', 'posts' => $recentPosts, 'color' => 'rose', 'empty' => 'No other news yet.']
    : ($post['type'] === 'tech_tip'
        ? ['title' => 'Related Drops', 'posts' => $categoryPosts, 'color' => 'emerald', 'empty' => 'No related posts yet.']
        : (count($categoryPosts)
            ? ['title' => 'Related Drops', 'posts' => $categoryPosts, 'color' => 'indigo', 'empty' => null]
            : ['title' => 'Recent Drops', 'posts' => $recentPosts, 'color' => 'indigo', 'empty' => 'No other posts yet.']));
@endphp

@section('content')
{{-- Preload the post hero (LCP) so it's discovered before CSS/JS parse. The dark
     band above it is CSS gradients only (no image), so the hero stays the LCP. --}}
@php $lcpSrc = $post['hero_image'] ?? $post['featured_image'] ?? null; @endphp
@if($lcpSrc)
@push('head')
@php $lcpWebp = \App\Support\ResponsiveImage::webpSrcset($lcpSrc); @endphp
@if($lcpWebp !== '')
<link rel="preload" as="image" type="image/webp" imagesrcset="{{ $lcpWebp }}" imagesizes="(min-width: 1024px) 768px, 100vw" fetchpriority="high">
@else
<link rel="preload" as="image" href="{{ $lcpSrc }}" fetchpriority="high">
@endif
@endpush
@endif

<x-reading-progress />

{{-- ── Dark intel band ─────────────────────────────────────────────────
     Same recipe as the category hero (gradient + dot grid + indigo
     hairlines) so every page reads as one site. Holds the type eyebrow,
     H1, byline, the one-line price-truth strip, and share. --}}
<section class="relative overflow-hidden"
    style="background: linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)">
    <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, rgba(99,102,241,0.18) 1px, transparent 1px); background-size: 28px 28px;"></div>
    <div class="absolute inset-0 pointer-events-none opacity-[0.04]" style="background-image: repeating-linear-gradient(45deg, white 0px, white 1px, transparent 0px, transparent 50%); background-size: 20px 20px;"></div>
    <div class="absolute right-0 top-1/2 -translate-y-1/2 w-80 h-80 rounded-full bg-indigo-600/10 blur-3xl pointer-events-none"></div>
    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent"></div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>

    <div class="relative z-10 max-w-6xl mx-auto px-4 py-12 lg:py-14 w-full">
        @if($firstCategory)
            <nav class="flex items-center gap-1.5 text-xs text-gray-500 mb-5">
                <a href="{{ route('home') }}" wire:navigate class="hover:text-indigo-400 transition-colors">Home</a>
                <svg class="w-3 h-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                <a href="{{ route('category', $firstCategory['slug']) }}" wire:navigate class="text-gray-300 font-medium hover:text-indigo-400 transition-colors">{{ $firstCategory['name'] }}</a>
            </nav>
        @endif

        <div class="flex flex-wrap items-center gap-3 mb-3">
            <span class="inline-flex items-center gap-1.5 {{ $eyebrowText }} text-xs font-bold uppercase tracking-[0.15em]">
                <span class="w-4 h-px {{ $eyebrowBar }}"></span>
                {{ $typeLabel }}
            </span>
            @if($post['type'] === 'tech_news' && $isBreaking)
                <span class="inline-flex items-center gap-1.5 text-[11px] bg-rose-600 text-white font-bold px-2.5 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    Breaking
                </span>
            @endif
        </div>

        <h1 class="text-4xl lg:text-5xl font-extrabold text-white leading-tight max-w-3xl">{{ $post['title'] }}</h1>

        <p class="mt-4 text-sm text-gray-400">
            By <span class="text-gray-300 font-medium">{{ $post['user']['name'] ?? '' }}</span> · {{ $post['published_at'] }}
            @if(!empty($post['read_minutes']))
                <span class="text-gray-500">· {{ $post['read_minutes'] }} min read</span>
            @endif
            @if($post['type'] !== 'tech_news')
                · <a href="{{ route('how-we-review') }}" wire:navigate class="underline decoration-gray-600 underline-offset-2 hover:text-indigo-300 transition-colors">How we review</a>
            @endif
        </p>

        {{-- One-line price-truth strip — renders nothing on tips/news/priceless. --}}
        <x-intel-strip :stats="$intelStats" />

        <div class="mt-5">
            <x-share-bar :url="route('posts.show', $post['slug'])" :short-url="$post['short_url']" :title="$post['title']" />
        </div>
    </div>
</section>

<div class="h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent"></div>

{{-- ── Body area (light surface) ───────────────────────────────────── --}}
<div class="max-w-6xl mx-auto px-4 py-12 grid grid-cols-1 lg:grid-cols-4 gap-10">
    <article class="lg:col-span-3 min-w-0">
        @if($post['type'] !== 'tech_news')
            <div class="mb-6">
                <x-affiliate-disclosure />
            </div>
        @endif

        {{-- Hero image — below the band, on the light surface (white pack-shots
             keep their background). Still eager/high-priority for LCP. --}}
        @if($post['hero_image'] || $post['featured_image'])
            <x-adaptive-image
                :src="$post['hero_image'] ?? $post['featured_image']"
                :alt="'Featured image for ' . $post['title']"
                :fit="$post['featured_image_fit'] ?? 'cover'"
                :position="$post['hero_image_position'] ?? $post['featured_image_position'] ?? 'center center'"
                class="w-full rounded-2xl border border-gray-200/80 max-h-96"
                wrapper-class="mb-10"
                sizes="(min-width: 1024px) 768px, 100vw"
                loading="eager"
                fetchpriority="high" />
        @endif

        {{-- Body — 68ch measure inside <x-article-body>, figures full column. --}}
        <x-article-body :sections="$sections" />

        {{-- ── End zone ──────────────────────────────────────────────
             Commerce block first: the product card is the single affiliate
             CTA; the tracked-price panel, buy-or-wait strip, and price-watch
             signup regroup around it (they carry no CTA / an email field, so
             the single-CTA rule holds). --}}
        @if(count($post['products']) > 0)
            <div class="mt-12 space-y-4">
                @foreach($post['products'] as $product)
                    <x-product-card :product="$product" :post-id="$post['id']" />
                    <x-price-history :stats="$product['price_intel'] ?? null" />
                    <x-buy-or-wait-strip :data="$product['buy_or_wait'] ?? null" />
                    @if(!empty($product['price_intel']))
                        @livewire('price-watch-signup', ['productId' => $product['id']])
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Verdict summary (rating + pros/cons) — the editorial payload. --}}
        <x-verdict-box :post="$post" />

        {{-- Worth-it vote — end-of-article engagement, kept away from the
             product card so the single affiliate CTA keeps its space. --}}
        <div class="mt-10">
            @livewire('worth-it-vote', ['postId' => $post['id']])
        </div>

        {{-- Meta footer — tags, source, byline card in one quiet zone so the
             end of the article isn't a stack of same-weight boxes. --}}
        <div class="mt-12 pt-8 border-t border-gray-200 space-y-6">
            @if(count($post['tags']) > 0)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400 mr-1">Tagged</span>
                    @foreach($post['tags'] as $tag)
                        <a href="{{ route('tag', $tag['slug']) }}" wire:navigate class="bg-white border border-gray-200 text-gray-600 text-xs px-2.5 py-1 rounded-full font-medium hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">#{{ $tag['name'] }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Source attribution for Tech News --}}
            @if($post['type'] === 'tech_news' && $post['source_url'])
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-rose-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                    </svg>
                    <p class="text-xs text-gray-500">
                        Source:
                        <a href="{{ $post['source_url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-rose-600 transition-colors">{{ $sourceDomain }}</a>
                    </p>
                </div>
            @endif

            {{-- Reddit attribution for Tech Tips --}}
            @if($post['type'] === 'tech_tip' && $post['source_url'])
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-orange-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z" />
                    </svg>
                    <p class="text-xs text-gray-500">
                        Summarized from a
                        <a href="{{ $post['source_url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600 transition-colors">Reddit community discussion</a>,
                        edited for clarity and accuracy.
                    </p>
                </div>
            @endif

            {{-- Author card --}}
            @if($post['user'])
                @php $authorHref = !empty($post['user']['slug']) ? route('author', $post['user']['slug']) : '#'; @endphp
                <div class="flex items-start gap-4 bg-white border border-gray-200 rounded-2xl p-5">
                    <a href="{{ $authorHref }}" class="flex-shrink-0">
                        @if(!empty($post['user']['avatar_url']))
                            <x-responsive-image :src="$post['user']['avatar_url']" :alt="$post['user']['name']" sizes="56px" loading="lazy" width="56" height="56"
                                class="w-14 h-14 rounded-full object-cover ring-2 ring-indigo-100 hover:ring-indigo-300 transition" />
                        @else
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center ring-2 ring-indigo-100 hover:ring-indigo-300 transition">
                                <span class="text-white font-bold text-xl">{{ \Illuminate\Support\Str::substr($post['user']['name'], 0, 1) }}</span>
                            </div>
                        @endif
                    </a>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Edited by</p>
                        <a href="{{ $authorHref }}" class="font-semibold text-gray-900 hover:text-indigo-600 transition-colors">{{ $post['user']['name'] }}</a>
                        @if(!empty($post['user']['bio']))
                            <p class="text-sm text-gray-500 mt-1">{{ $post['user']['bio'] }}</p>
                        @endif
                        <a href="{{ $authorHref }}" class="inline-flex items-center gap-1 mt-2 text-xs text-indigo-500 hover:text-indigo-700 font-medium transition-colors">View all posts →</a>
                    </div>
                </div>
            @endif
        </div>
    </article>

    {{-- Sidebar — sticky, trimmed to On-this-page + Related Products + one list. --}}
    <aside class="lg:sticky lg:top-20 self-start space-y-6">
        @if(count($toc) >= 2)
            <nav class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm" aria-label="On this page">
                <h3 class="flex items-center gap-2 font-bold text-gray-800 text-xs uppercase tracking-widest mb-3">
                    <span class="w-3 h-px bg-indigo-400"></span>
                    On this page
                </h3>
                <ul class="space-y-1.5 text-sm">
                    @foreach($toc as $h)
                        <li>
                            <a href="#{{ $h['slug'] }}" class="block leading-snug text-gray-600 hover:text-indigo-600 transition-colors">{{ $h['text'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if(count($relatedProducts) > 0)
            <x-related-products :products="$relatedProducts" :post-id="$post['id']" :color="$sectionColor" />
        @endif

        <x-sidebar-section :title="$sidebarList['title']" :posts="collect($sidebarList['posts'])->take(5)" :empty-label="$sidebarList['empty']" :color="$sidebarList['color']" />
    </aside>
</div>
@endsection
