<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>OG Card Preview · GadgetDrop</title>
    @vite(['resources/css/app.css'])
    <style>[x-cloak]{display:none}</style>
</head>
<body class="bg-gray-100 text-gray-900 font-sans antialiased">
    <div class="max-w-6xl mx-auto px-4 py-10">
        <header class="mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight">Social Share Card Preview</h1>
            <p class="mt-2 text-gray-600">
                How each post's auto-generated 1200&times;630 card renders when shared.
                Cards are served live from <code class="text-indigo-600">/og/posts/&lbrace;slug&rbrace;.jpg</code> —
                what you see here is exactly what Twitter/X, Facebook, LinkedIn, etc. will fetch.
            </p>
            <p class="mt-1 text-sm text-gray-500">{{ $posts->count() }} published posts · indigo theme = posts/reviews · rose theme = tech news</p>
        </header>

        {{-- Generic fallback — used on home, category, news index, search, about, etc. --}}
        <section class="mb-10">
            <h2 class="mb-2 text-lg font-bold">Generic fallback</h2>
            <p class="mb-3 max-w-2xl text-sm text-gray-600">
                Shown for any page without its own card — home, category, news index, search, and the static pages.
            </p>
            <a href="{{ route('og.default') }}" target="_blank"
               class="block max-w-xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                <img src="{{ route('og.default') }}" alt="GadgetDrop generic share card"
                     width="1200" height="630" loading="lazy"
                     class="block w-full aspect-[1200/630] object-cover bg-gray-900">
                <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'gadgetdrop.tech' }}</div>
                    <div class="mt-0.5 truncate font-semibold text-gray-900">GadgetDrop | Daily Tech Picks, Gadget Reviews &amp; Buying Guides</div>
                    <div class="mt-0.5 line-clamp-2 text-sm text-gray-500">Daily tech picks, gadget reviews, and buying guides. Find the best gear at the best price, delivered fresh every day.</div>
                </div>
            </a>
        </section>

        <h2 class="mb-4 text-lg font-bold">Per-post cards</h2>

        @if($posts->isEmpty())
            <div class="rounded-xl bg-white p-8 text-center text-gray-500 shadow-sm">
                No published posts with a featured image yet.
            </div>
        @else
            <div class="grid gap-8 sm:grid-cols-2">
                @foreach($posts as $post)
                    <article>
                        <div class="mb-2 flex items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold uppercase tracking-wide
                                {{ $post['type'] === 'tech_news' ? 'bg-rose-100 text-rose-700' : 'bg-indigo-100 text-indigo-700' }}">
                                {{ str_replace('_', ' ', $post['type']) }}
                            </span>
                            <a href="{{ $post['url'] }}" target="_blank"
                               class="text-xs text-gray-400 hover:text-gray-600 hover:underline">view post &rarr;</a>
                        </div>

                        {{-- Facebook / LinkedIn style large share preview --}}
                        <a href="{{ $post['card'] }}" target="_blank"
                           class="block overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                            <img src="{{ $post['card'] }}" alt="Share card for {{ $post['title'] }}"
                                 width="1200" height="630" loading="lazy"
                                 class="block w-full aspect-[1200/630] object-cover bg-gray-900">
                            <div class="border-t border-gray-200 bg-gray-50 px-4 py-3">
                                <div class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ $post['domain'] }}</div>
                                <div class="mt-0.5 truncate font-semibold text-gray-900">{{ $post['title'] }}</div>
                                @if($post['excerpt'])
                                    <div class="mt-0.5 line-clamp-2 text-sm text-gray-500">{{ $post['excerpt'] }}</div>
                                @endif
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
