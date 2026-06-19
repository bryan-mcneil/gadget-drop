<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $serverMeta['title'] ?? config('app.name', 'GadgetDrop') }}</title>
        @isset($serverMeta)
        @if(!empty($serverMeta['description']))
        <meta name="description" content="{{ $serverMeta['description'] }}">
        @endif
        <meta property="og:site_name"   content="GadgetDrop">
        <meta property="og:type"        content="{{ $serverMeta['og_type'] ?? 'article' }}">
        <meta property="og:title"       content="{{ $serverMeta['title'] ?? config('app.name', 'GadgetDrop') }}">
        <meta property="og:url"         content="{{ $serverMeta['canonical'] ?? url()->current() }}">
        @if(!empty($serverMeta['description']))
        <meta property="og:description" content="{{ $serverMeta['description'] }}">
        @endif
        @php($ogImage = ($serverMeta['og_image'] ?? null) ?: route('og.default'))
        @php($ogImageAlt = $serverMeta['og_image_alt'] ?? ($serverMeta['title'] ?? 'GadgetDrop'))
        <meta property="og:image"        content="{{ $ogImage }}">
        <meta property="og:image:width"  content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt"    content="{{ $ogImageAlt }}">

        {{-- Twitter / X card --}}
        <meta name="twitter:card"        content="summary_large_image">
        <meta name="twitter:title"       content="{{ $serverMeta['title'] ?? config('app.name', 'GadgetDrop') }}">
        @if(!empty($serverMeta['description']))
        <meta name="twitter:description" content="{{ $serverMeta['description'] }}">
        @endif
        <meta name="twitter:image"       content="{{ $ogImage }}">
        <meta name="twitter:image:alt"   content="{{ $ogImageAlt }}">

        <link rel="canonical" href="{{ $serverMeta['canonical'] ?? url()->current() }}">
        @endisset

        <!-- Favicons / app icons — .ico is what Google Search & older browsers
             request by host convention; svg/png for crisp modern rendering. -->
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @if(config('services.adsense.enabled'))
        <!-- Resource hints for third-party origins -->
        <link rel="dns-prefetch" href="https://pagead2.googlesyndication.com">
        <link rel="preconnect" href="https://pagead2.googlesyndication.com" crossorigin>
        @endif

        <!-- Fonts — self-hosted (public/fonts/figtree). @font-face lives in
             app.css (render-blocking) with font-display:optional; preload the
             woff2 so Figtree is ready at first paint and never swaps in
             (no FOUT/bold flash). Served same-origin, so the CDN caches them. -->
        <link rel="preload" as="font" type="font/woff2" crossorigin href="/fonts/figtree/figtree-latin-400-normal.woff2">
        <link rel="preload" as="font" type="font/woff2" crossorigin href="/fonts/figtree/figtree-latin-500-normal.woff2">
        <link rel="preload" as="font" type="font/woff2" crossorigin href="/fonts/figtree/figtree-latin-600-normal.woff2">

        <!-- Google Consent Mode v2 — defaults denied until user accepts -->
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent', 'default', {
                ad_storage:          'denied',
                ad_user_data:        'denied',
                ad_personalization:  'denied',
                analytics_storage:   'denied',
                wait_for_update:     500,
            });
            // Restore consent if user already accepted
            var _gc = localStorage.getItem('gadgetdrop_consent');
            if (_gc === 'accepted') {
                gtag('consent', 'update', {
                    ad_storage:         'granted',
                    ad_user_data:       'granted',
                    ad_personalization: 'granted',
                    analytics_storage:  'granted',
                });
            }
        </script>

        @if(config('services.adsense.enabled'))
        <!-- Google AdSense -->
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('services.adsense.client') }}"
            crossorigin="anonymous"></script>
        @endif

        @isset($serverJsonLd)
        <script type="application/ld+json">{!! $serverJsonLd !!}</script>
        @endisset

        @stack('head')

        @php($publicEntries = ['resources/css/app.css', 'resources/js/app.js'])
        {{-- Tool Alpine components ship only on /tools/* pages so the homepage and
             articles don't download the heavy image-processing/minifier code. --}}
        @if(request()->routeIs('tools.*'))
            @php($publicEntries[] = 'resources/js/tools.js')
        @endif
        @vite($publicEntries)
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-50 flex flex-col">
            <x-public.header :navigation="$navigation" />

            <main class="flex-1">
                @yield('content')
            </main>

            <x-public.footer />
        </div>

        <x-cookie-consent />

        @livewireScripts
    </body>
</html>
