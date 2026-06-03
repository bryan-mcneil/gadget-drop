<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @isset($serverMeta)
        <title>{{ $serverMeta['title'] }}</title>
        @if($serverMeta['description'])
        <meta name="description" content="{{ $serverMeta['description'] }}">
        @endif
        <meta property="og:type"        content="{{ $serverMeta['og_type'] ?? 'article' }}">
        <meta property="og:title"       content="{{ $serverMeta['title'] }}">
        <meta property="og:url"         content="{{ $serverMeta['canonical'] }}">
        @if($serverMeta['description'])
        <meta property="og:description" content="{{ $serverMeta['description'] }}">
        @endif
        @if($serverMeta['og_image'])
        <meta property="og:image"       content="{{ $serverMeta['og_image'] }}">
        @endif
        <link rel="canonical" href="{{ $serverMeta['canonical'] }}">
        @else
        <title inertia>{{ config('app.name', 'GadgetDrop') }}</title>
        @endisset

        <!-- Favicon -->
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

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

        <!-- Google AdSense -->
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3856395634564582"
            crossorigin="anonymous"></script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
        @isset($serverJsonLd)
        <script type="application/ld+json">{!! $serverJsonLd !!}</script>
        @endisset
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
