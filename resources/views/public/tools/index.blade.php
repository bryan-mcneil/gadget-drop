@extends('layouts.public')

@php
    $categories = [
        ['key' => 'developer', 'label' => 'Developer Tools', 'description' => 'Validate, minify, and debug code right in your browser.', 'icon' => 'code-bracket'],
        ['key' => 'image',     'label' => 'Image Tools',     'description' => 'Convert, resize, and crop images without uploading anything.', 'icon' => 'photo'],
    ];
    $toolsColl = collect($tools);
    $catKeys = collect($categories)->pluck('key');
    $uncategorized = $toolsColl->filter(fn ($t) => !$catKeys->contains($t['category'] ?? null));
@endphp

@section('content')
    {{-- Hero --}}
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-[100rem] mx-auto px-4 py-14 text-center">
            <div class="inline-flex items-center gap-2 bg-amber-50 text-amber-700 text-xs font-semibold uppercase tracking-widest px-3 py-1.5 rounded-full mb-4">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" /></svg>
                Free Tools
            </div>
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">Online Tools That Actually <span class="text-amber-500">Work</span></h1>
            <p class="text-lg text-gray-500 max-w-xl mx-auto leading-relaxed">Fast, free, and private. Everything runs in your browser, nothing is uploaded to a server.</p>
        </div>
    </div>

    {{-- Categorized tool grid --}}
    <div class="max-w-[100rem] mx-auto px-4 py-12 space-y-12">
        @foreach($categories as $cat)
            @php $catTools = $toolsColl->where('category', $cat['key']); @endphp
            @if($catTools->count() > 0)
                <section>
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                            <x-tool-icon :icon="$cat['icon']" class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">{{ $cat['label'] }}</h2>
                            <p class="text-sm text-gray-500">{{ $cat['description'] }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                        @foreach($catTools as $tool)
                            <x-tools.card :slug="$tool['slug']" :name="$tool['name']" :description="$tool['description']" :icon="$tool['icon']" />
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if($uncategorized->count() > 0)
            <section>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                    @foreach($uncategorized as $tool)
                        <x-tools.card :slug="$tool['slug']" :name="$tool['name']" :description="$tool['description']" :icon="$tool['icon']" />
                    @endforeach
                </div>
            </section>
        @endif

        @if($toolsColl->count() === 0)
            <p class="text-center text-gray-400 py-16">Tools coming soon.</p>
        @endif
    </div>

    {{-- Privacy callout --}}
    <div class="max-w-[100rem] mx-auto px-4 pb-16">
        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-8 text-center">
            <h2 class="text-lg font-bold text-gray-900 mb-2">100% Private, 100% Free</h2>
            <p class="text-sm text-gray-500 max-w-lg mx-auto">Every tool on this page runs entirely in your browser. Your code, JSON, and images never leave your device. No accounts, no limits, no cost.</p>
        </div>
    </div>
@endsection
