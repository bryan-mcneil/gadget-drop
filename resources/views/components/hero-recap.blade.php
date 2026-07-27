@props(['post'])

@php
    // Hero slot for the post page (plan 10.7). With a recap video: a poster-first
    // <video> — the poster is the existing hero WebP, so the painted poster stays
    // the LCP exactly as the plain image was; the mp4 only fetches near-viewport
    // (preload="none"). Without one: the plain adaptive-image hero, unchanged.
    $heroSrc = $post['hero_image'] ?? $post['featured_image'] ?? null;
    $recapUrl = $post['recap_video_url'] ?? null;
    $poster = $recapUrl ? (\App\Support\ResponsiveImage::webpVariantUrl($heroSrc) ?? $heroSrc) : null;
@endphp

@if($recapUrl && $heroSrc)
    {{-- aspect-[16/9] box reserves the space before any media loads (no CLS).
         muted + playsinline let mobile autoplay fire; no loop — plays once. --}}
    <div class="relative aspect-[16/9] rounded-2xl border border-gray-200/80 overflow-hidden bg-slate-950 mb-10"
        x-data="heroRecap">
        <video x-ref="video"
            class="absolute inset-0 w-full h-full object-cover"
            muted
            playsinline
            preload="none"
            poster="{{ $poster }}"
            aria-label="Video recap: {{ $post['title'] }}">
            <source src="{{ $recapUrl }}" type="{{ str_ends_with($recapUrl, '.webm') ? 'video/webm' : 'video/mp4' }}">
        </video>

        {{-- Reduced-motion path: autoplay is suppressed in JS, this overlay plays
             it on demand instead. x-cloak keeps it hidden until Alpine decides. --}}
        <button type="button" x-cloak x-show="showPlay" @click="play()"
            class="absolute inset-0 z-10 flex items-center justify-center group"
            aria-label="Play video recap">
            <span class="flex items-center justify-center w-16 h-16 rounded-full bg-white/90 shadow-lg ring-1 ring-black/10 group-hover:bg-white transition-colors">
                <svg class="w-7 h-7 text-indigo-600 translate-x-0.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
            </span>
        </button>
    </div>
@elseif($heroSrc)
    <x-adaptive-image
        :src="$heroSrc"
        :alt="'Featured image for ' . $post['title']"
        :fit="$post['featured_image_fit'] ?? 'cover'"
        :position="$post['hero_image_position'] ?? $post['featured_image_position'] ?? 'center center'"
        class="w-full rounded-2xl border border-gray-200/80 max-h-96"
        wrapper-class="mb-10"
        sizes="(min-width: 1024px) 768px, 100vw"
        loading="eager"
        fetchpriority="high" />
@endif
