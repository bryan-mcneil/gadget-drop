@extends('layouts.public')

@php
    $fmtNum = function ($n) {
        return $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k' : (string) $n;
    };
    $articles = collect($posts)->filter(fn ($p) => $p['type'] !== 'tech_tip')->values();
    $techTips = collect($posts)->filter(fn ($p) => $p['type'] === 'tech_tip')->values();
@endphp

@section('content')
    {{-- Hero --}}
    <div class="relative overflow-hidden bg-white border-b border-gray-100">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-indigo-100/60 blur-3xl"></div>
            <div class="absolute -bottom-16 -left-16 w-72 h-72 rounded-full bg-violet-100/50 blur-3xl"></div>
        </div>

        <div class="relative max-w-4xl mx-auto px-4 py-16 flex flex-col sm:flex-row items-center sm:items-start gap-8">
            <div class="flex-shrink-0">
                @if(!empty($author['avatar_url']))
                    <img src="{{ $author['avatar_url'] }}" alt="{{ $author['name'] }}" class="w-28 h-28 rounded-full object-cover ring-4 ring-white shadow-xl" />
                @else
                    <div class="w-28 h-28 rounded-full ring-4 ring-white shadow-xl bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center">
                        <span class="text-white font-extrabold text-5xl leading-none select-none">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($author['name'], 0, 1)) }}</span>
                    </div>
                @endif
            </div>

            <div class="text-center sm:text-left min-w-0">
                <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-1">GadgetDrop Writer</p>
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">{{ $author['name'] }}</h1>

                @if(!empty($author['bio']))
                    <p class="mt-3 text-gray-500 max-w-lg leading-relaxed">{{ $author['bio'] }}</p>
                @endif

                <div class="mt-5 flex flex-wrap justify-center sm:justify-start gap-x-6 gap-y-2">
                    <div class="flex items-center gap-1.5 text-sm text-gray-500"><span class="font-semibold text-gray-800">{{ $postCount }}</span><span>posts</span></div>
                    <div class="flex items-center gap-1.5 text-sm text-gray-500"><span class="font-semibold text-gray-800">{{ $fmtNum($totalViews) }}</span><span>total views</span></div>
                    @if(!empty($author['since']))
                        <div class="flex items-center gap-1.5 text-sm text-gray-500"><span class="font-semibold text-gray-800">{{ $author['since'] }}</span><span>first post</span></div>
                    @endif
                </div>

                <p class="mt-4 text-xs text-gray-400 max-w-sm text-center sm:text-left">
                    {{ $author['name'] }} is a GadgetDrop editorial persona: a distinct writing voice maintained by the GadgetDrop team.
                    <a href="{{ route('about') }}" class="underline hover:text-gray-600">Learn more →</a>
                </p>
            </div>
        </div>
    </div>

    {{-- Post sections --}}
    <div class="max-w-4xl mx-auto px-4 py-12 space-y-14">
        @if($articles->count() > 0)
            <section>
                <div class="flex items-center gap-3">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-indigo-500">Articles &amp; Reviews</h2>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">{{ $articles->count() }}</span>
                    <span class="flex-1 h-px bg-gray-100"></span>
                </div>
                <div class="grid sm:grid-cols-2 gap-6 mt-6">
                    @foreach($articles as $p)
                        <x-author-post-card :post="$p" />
                    @endforeach
                </div>
            </section>
        @endif

        @if($techTips->count() > 0)
            <section>
                <div class="flex items-center gap-3">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-emerald-600">Tech Tips</h2>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">{{ $techTips->count() }}</span>
                    <span class="flex-1 h-px bg-gray-100"></span>
                </div>
                <div class="grid sm:grid-cols-2 gap-6 mt-6">
                    @foreach($techTips as $p)
                        <x-author-post-card :post="$p" emerald />
                    @endforeach
                </div>
            </section>
        @endif

        @if(count($posts) === 0)
            <p class="text-center text-gray-400 py-24 text-sm">No published posts yet.</p>
        @endif
    </div>
@endsection
