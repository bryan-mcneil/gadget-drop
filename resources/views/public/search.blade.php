@extends('layouts.public')

@php
    $total = count($posts) + count($categories) + count($tags);
@endphp

@push('head')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    {{-- Hero search bar --}}
    <section class="relative overflow-hidden py-14 md:py-20" style="background: linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)">
        <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, rgba(99,102,241,0.18) 1px, transparent 1px); background-size: 28px 28px;"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[300px] rounded-full bg-indigo-700/15 blur-[80px] pointer-events-none"></div>
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>

        <div class="relative max-w-2xl mx-auto px-4 text-center">
            <span class="inline-flex items-center gap-1.5 text-indigo-400 text-xs font-bold uppercase tracking-[0.15em] mb-4">
                <span class="w-4 h-px bg-indigo-400"></span>
                Search
                <span class="w-4 h-px bg-indigo-400"></span>
            </span>
            <h1 class="text-3xl md:text-4xl font-extrabold text-white mb-8 tracking-tight">Find your next drop</h1>

            <form action="{{ route('search') }}" method="GET">
                <div class="flex gap-2 items-center bg-white/95 rounded-2xl p-2 shadow-xl shadow-black/30">
                    <svg class="w-5 h-5 text-gray-400 ml-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                    <input type="search" name="q" value="{{ $query }}" placeholder="Search posts, categories, tags…" autofocus
                        class="flex-1 bg-transparent text-gray-900 placeholder-gray-400 text-base py-1.5 px-2 focus:outline-none border-0 ring-0" />
                    <button type="submit" class="flex-shrink-0 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm px-5 py-2.5 rounded-xl transition-colors">Search</button>
                </div>
            </form>

            @if($query && $total > 0)
                <p class="mt-5 text-indigo-300/70 text-sm">
                    {{ $total }} result{{ $total !== 1 ? 's' : '' }} for
                    <span class="text-indigo-300 font-semibold">"{{ $query }}"</span>
                </p>
            @endif
        </div>
    </section>

    <div class="h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent"></div>

    <div class="max-w-4xl mx-auto px-4 py-12 space-y-10">
        @if(!$query)
            <div class="text-center space-y-8 py-6">
                <p class="text-gray-400 text-sm">Start typing to search posts, categories, and tags.</p>
                @if(count($popularTags) > 0)
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Popular Tags</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            @foreach(collect($popularTags)->take(24) as $tag)
                                <a href="{{ route('search') }}?q={{ urlencode($tag['name']) }}" wire:navigate class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-full font-medium transition-colors">#{{ $tag['name'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if($query && $total === 0)
            <div class="text-center py-16 space-y-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-50 mb-2">
                    <svg class="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                </div>
                <p class="text-lg font-bold text-gray-900">No results for "{{ $query }}"</p>
                <p class="text-sm text-gray-400">Try a different keyword or browse a category from the nav.</p>
                @if(count($popularTags) > 0)
                    <div class="pt-4">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Try these tags</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            @foreach(collect($popularTags)->take(12) as $tag)
                                <a href="{{ route('search') }}?q={{ urlencode($tag['name']) }}" wire:navigate class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-full font-medium transition-colors">#{{ $tag['name'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if(count($categories) > 0)
            <section>
                <x-search-label>Categories</x-search-label>
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach($categories as $cat)
                        <a href="{{ route('category', $cat['slug']) }}" wire:navigate class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-100 rounded-xl shadow-sm hover:border-indigo-200 hover:shadow-md text-sm font-semibold text-gray-800 hover:text-indigo-700 transition-all group">
                            {{ $cat['name'] }}
                            <span class="text-xs font-medium text-gray-400 group-hover:text-indigo-400 transition-colors bg-gray-50 group-hover:bg-indigo-50 px-1.5 py-0.5 rounded-full">{{ $cat['posts_count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if(count($tags) > 0)
            <section>
                <x-search-label>Tags</x-search-label>
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach($tags as $tag)
                        <a href="{{ route('search') }}?q={{ urlencode($tag['name']) }}" wire:navigate class="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-full font-medium transition-colors">#{{ $tag['name'] }}</a>
                    @endforeach
                </div>
            </section>
        @endif

        @if(count($posts) > 0)
            <section>
                <x-search-label>{{ count($posts) }} Post{{ count($posts) !== 1 ? 's' : '' }}</x-search-label>
                <div class="mt-4 space-y-4">
                    @foreach($posts as $post)
                        <a href="{{ route('posts.show', $post['slug']) }}" wire:navigate class="group flex gap-4 items-start bg-white border border-gray-100 rounded-2xl p-4 shadow-sm hover:shadow-xl transition-shadow duration-300">
                            <div class="relative flex-shrink-0 w-24 h-24 rounded-xl overflow-hidden bg-indigo-50">
                                @if(!empty($post['featured_image']))
                                    <x-responsive-image :src="$post['featured_image']" :alt="$post['title']" loading="lazy" width="96" height="96" sizes="96px" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center"><span class="text-indigo-300 font-black text-2xl select-none">G</span></div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="text-xs text-gray-400 tracking-wide">{{ $post['published_at'] }}</p>
                                    @if($post['type'] === 'tech_tip')
                                        <span class="text-xs bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full">Tech Tip</span>
                                    @endif
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition-colors leading-snug text-base">{{ $post['title'] }}</h3>
                                @if(!empty($post['excerpt']))
                                    <p class="mt-1.5 text-sm text-gray-500 line-clamp-2">{{ $post['excerpt'] }}</p>
                                @endif
                                <div class="mt-3 flex items-center gap-1 text-xs font-semibold text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    Read more
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
