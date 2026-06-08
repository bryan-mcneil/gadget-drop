@extends('layouts.public')

@php
    $isWithin24h = function ($iso) {
        if (!$iso) return false;
        try { return \Illuminate\Support\Carbon::parse($iso)->gt(now()->subDay()); } catch (\Throwable $e) { return false; }
    };
    $extractDomain = function ($url) {
        if (!$url) return null;
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? preg_replace('/^www\./', '', $host) : null;
    };
    $fmtDate = function ($d) {
        if (!$d) return '';
        try { return \Illuminate\Support\Carbon::parse($d)->format('M j, Y'); } catch (\Throwable $e) { return $d; }
    };
@endphp

@section('content')
    <div class="bg-white border-b border-gray-200">
        <div class="h-1 bg-gradient-to-r from-rose-500 via-red-500 to-rose-400"></div>
        <div class="max-w-6xl mx-auto px-4 py-10">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center gap-1.5 bg-rose-600 text-white text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                    Live Coverage
                </span>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Tech News</h1>
            <p class="text-gray-500 mt-1 text-sm">The latest in tech: breaking stories, product launches, and industry moves.</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-10">
        @if($posts->total() === 0)
            <div class="text-center py-24 text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" /></svg>
                <p class="font-medium">No news yet, check back soon.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($posts as $i => $post)
                    @php
                        $featured = $i === 0 && $posts->currentPage() === 1;
                        $isBreaking = $isWithin24h($post['published_at_iso']);
                        $domain = $extractDomain($post['source_url']);
                    @endphp

                    @if($featured)
                        <a href="{{ route('posts.show', $post['slug']) }}" class="sm:col-span-2 lg:col-span-3 group block bg-white rounded-2xl border border-rose-100 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                            <div class="h-1.5 bg-gradient-to-r from-rose-500 via-red-500 to-rose-400"></div>
                            <div class="flex flex-col md:flex-row">
                                @if(!empty($post['featured_image']))
                                    <div class="md:w-2/5 shrink-0">
                                        <img src="{{ $post['featured_image'] }}" alt="{{ $post['title'] }}" class="w-full h-56 md:h-full object-cover" style="object-position: {{ $post['featured_image_position'] ?? 'center center' }}" />
                                    </div>
                                @endif
                                <div class="p-6 flex flex-col justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-3 flex-wrap">
                                            @if($isBreaking)<x-news.breaking-badge />@endif
                                            @if($domain)<x-news.source-chip :domain="$domain" />@endif
                                        </div>
                                        <h2 class="text-xl font-bold text-gray-900 group-hover:text-rose-700 transition-colors leading-snug mb-2">{{ $post['title'] }}</h2>
                                        @if(!empty($post['excerpt']))<p class="text-sm text-gray-500 line-clamp-3">{{ $post['excerpt'] }}</p>@endif
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-gray-400">
                                        <span>{{ $fmtDate($post['published_at']) }}</span>
                                        <span>·</span>
                                        <span>{{ $post['read_minutes'] }} min read</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @else
                        <a href="{{ route('posts.show', $post['slug']) }}" class="group block bg-white rounded-xl border border-gray-200 hover:border-rose-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
                            <div class="h-1 bg-gradient-to-r from-rose-400 to-red-400 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            @if(!empty($post['featured_image']))
                                <div class="h-44 overflow-hidden">
                                    <img src="{{ $post['featured_image'] }}" alt="{{ $post['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" style="object-position: {{ $post['featured_image_position'] ?? 'center center' }}" />
                                </div>
                            @endif
                            <div class="p-4 space-y-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @if($isBreaking)<x-news.breaking-badge />@endif
                                    @if($domain)<x-news.source-chip :domain="$domain" />@endif
                                </div>
                                <h3 class="text-sm font-bold text-gray-900 group-hover:text-rose-700 transition-colors leading-snug line-clamp-3">{{ $post['title'] }}</h3>
                                @if(!empty($post['excerpt']))<p class="text-xs text-gray-500 line-clamp-2">{{ $post['excerpt'] }}</p>@endif
                                <div class="flex items-center justify-between text-xs text-gray-400 pt-1 border-t border-gray-100">
                                    <span>{{ $fmtDate($post['published_at']) }}</span>
                                    <span>{{ $post['read_minutes'] }} min read</span>
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>

            @if($posts->lastPage() > 1)
                <div class="mt-12 flex items-center justify-center gap-3">
                    @if($posts->currentPage() > 1)
                        <a href="{{ $posts->previousPageUrl() }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                            Previous
                        </a>
                    @endif
                    <span class="text-sm text-gray-500">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                    @if($posts->currentPage() < $posts->lastPage())
                        <a href="{{ $posts->nextPageUrl() }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Next
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    @endif
                </div>
            @endif
        @endif
    </div>
@endsection
