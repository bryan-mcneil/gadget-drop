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
@endphp

@section('content')
    {{-- Preload the post hero (LCP) so it's discovered before CSS/JS parse. --}}
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

    <div class="max-w-6xl mx-auto px-4 py-12 grid grid-cols-1 lg:grid-cols-4 gap-10">
        <article class="lg:col-span-3">
            {{-- Post type accent bar --}}
            <div class="h-1 rounded-full mb-6 -mx-1 bg-gradient-to-r {{ $post['type'] === 'tech_news' ? 'from-rose-500 via-red-500 to-rose-400' : ($post['type'] === 'tech_tip' ? 'from-emerald-500 via-green-500 to-emerald-400' : 'from-indigo-500 via-violet-500 to-indigo-400') }}"></div>

            {{-- Header --}}
            <div class="mb-6">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    @if($post['type'] === 'tech_tip')
                        <span class="inline-flex items-center gap-1 text-xs bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1 rounded-full">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            Tech Tip
                        </span>
                    @endif
                    @if($post['type'] === 'tech_news')
                        @if($isBreaking)
                            <span class="inline-flex items-center gap-1.5 text-xs bg-rose-600 text-white font-bold px-2.5 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                Breaking
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs bg-rose-100 text-rose-700 font-bold px-2.5 py-1 rounded-full">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                                Tech News
                            </span>
                        @endif
                    @endif
                    @foreach($post['categories'] as $c)
                        <a href="{{ route('category', $c['slug']) }}" wire:navigate class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-medium">{{ $c['name'] }}</a>
                    @endforeach
                </div>
                <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">{{ $post['title'] }}</h1>
                <div class="mt-2 space-y-1">
                    <p class="text-sm text-gray-500">
                        By {{ $post['user']['name'] ?? '' }} · {{ $post['published_at'] }}
                        @if(!empty($post['read_minutes']))
                            <span class="ml-2 text-gray-400">· {{ $post['read_minutes'] }} min read</span>
                        @endif
                    </p>
                    <x-share-bar :url="route('posts.show', $post['slug'])" :short-url="$post['short_url']" :title="$post['title']" />
                    @if($post['type'] !== 'tech_news')
                        <x-affiliate-disclosure />
                    @endif
                </div>
            </div>

            @if($post['hero_image'] || $post['featured_image'])
                <x-adaptive-image
                    :src="$post['hero_image'] ?? $post['featured_image']"
                    :alt="'Featured image for ' . $post['title']"
                    :fit="$post['featured_image_fit'] ?? 'cover'"
                    :position="$post['hero_image_position'] ?? $post['featured_image_position'] ?? 'center center'"
                    class="w-full rounded-xl max-h-96"
                    wrapper-class="mb-8"
                    sizes="(min-width: 1024px) 768px, 100vw"
                    loading="eager"
                    fetchpriority="high" />
            @endif

            {{-- Products --}}
            @if(count($post['products']) > 0)
                <div class="mb-8 space-y-4">
                    @foreach($post['products'] as $product)
                        <x-product-card :product="$product" :post-id="$post['id']" />
                    @endforeach
                </div>
            @endif

            {{-- Body — split into thirds with inline images between sections --}}
            <x-article-body :sections="$sections" />

            {{-- Repeat CTA after body --}}
            @if(count($post['products']) > 0)
                <div class="mt-10 pt-8 border-t border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Check Current Prices</h2>
                    <div class="space-y-3">
                        @foreach($post['products'] as $product)
                            <div class="flex items-center justify-between gap-4 bg-gray-50 rounded-xl px-5 py-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $product['name'] }}</p>
                                    @if(!empty($product['price']))<p class="text-sm text-gray-500">${{ $product['price'] }}</p>@endif
                                </div>
                                <a href="{{ route('affiliate.redirect', ['product' => $product['id'], 'post' => $post['id']]) }}"
                                    target="_blank" rel="nofollow sponsored"
                                    class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2">
                                    View on Amazon →
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Tags --}}
            @if(count($post['tags']) > 0)
                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach($post['tags'] as $tag)
                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full font-medium">#{{ $tag['name'] }}</span>
                    @endforeach
                </div>
            @endif

            {{-- Source attribution for Tech News --}}
            @if($post['type'] === 'tech_news' && $post['source_url'])
                <div class="mt-6 pt-5 border-t border-rose-100 flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-rose-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                    <p class="text-xs text-gray-400">
                        Source:
                        <a href="{{ $post['source_url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-rose-600 transition-colors">{{ $sourceDomain }}</a>
                    </p>
                </div>
            @endif

            {{-- Reddit attribution for Tech Tips --}}
            @if($post['type'] === 'tech_tip' && $post['source_url'])
                <div class="mt-6 pt-5 border-t border-gray-100 flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-orange-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>
                    <p class="text-xs text-gray-400">
                        Summarized from a
                        <a href="{{ $post['source_url'] }}" target="_blank" rel="nofollow noopener" class="underline hover:text-gray-600 transition-colors">Reddit community discussion</a>,
                        edited for clarity and accuracy.
                    </p>
                </div>
            @endif

            {{-- Author card --}}
            @if($post['user'])
                @php $authorHref = !empty($post['user']['slug']) ? route('author', $post['user']['slug']) : '#'; @endphp
                <div class="mt-8 flex items-start gap-4 bg-white border border-gray-200 rounded-xl p-5">
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
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Written by</p>
                        <a href="{{ $authorHref }}" class="font-semibold text-gray-900 hover:text-indigo-600 transition-colors">{{ $post['user']['name'] }}</a>
                        @if(!empty($post['user']['bio']))
                            <p class="text-sm text-gray-500 mt-1">{{ $post['user']['bio'] }}</p>
                        @endif
                        <a href="{{ $authorHref }}" class="inline-flex items-center gap-1 mt-2 text-xs text-indigo-500 hover:text-indigo-700 font-medium transition-colors">View all posts →</a>
                    </div>
                </div>
            @endif
        </article>

        {{-- Sidebar --}}
        <aside class="lg:pl-6 space-y-8">
            @if(count($relatedProducts) > 0)
                <x-related-products :products="$relatedProducts" :post-id="$post['id']" :color="$sectionColor" />
            @endif

            @if($post['type'] === 'tech_news')
                <x-sidebar-section title="More News" :posts="$recentPosts" empty-label="No other news yet." color="rose" />
                <x-sidebar-section title="Related Stories" :posts="$categoryPosts" :empty-label="null" color="rose" />
            @elseif($post['type'] === 'tech_tip')
                <x-sidebar-section title="Related Drops" :posts="$categoryPosts" empty-label="No related posts yet." color="emerald" />
            @else
                <x-sidebar-section title="Recent Drops" :posts="$recentPosts" empty-label="No other posts yet." />
            @endif

            @if(config('services.adsense.enabled'))
                <div class="min-h-[250px]">
                    <x-ad-unit slot="YOUR_AD_SLOT_ID" />
                </div>
            @endif

            @if($post['type'] === 'tech_news')
                <x-sidebar-section title="From Same Tags" :posts="$tagPosts" :empty-label="null" color="rose" />
            @elseif($post['type'] === 'tech_tip')
                <x-sidebar-section title="From Same Tags" :posts="$tagPosts" :empty-label="null" color="emerald" />
            @else
                <x-sidebar-section title="Related Drops" :posts="$categoryPosts" :empty-label="null" />
                <x-sidebar-section title="From Same Tags" :posts="$tagPosts" :empty-label="null" />
            @endif
        </aside>
    </div>
@endsection
