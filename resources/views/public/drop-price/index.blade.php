@extends('layouts.public')

@section('content')
    {{-- Dark heat-band hero, matching the homepage game's own section so the
         archive reads as a continuation of the same game, not a separate page. --}}
    <x-drop-price.band>
        <div class="relative max-w-6xl mx-auto px-4 py-14 md:py-16">
            <nav class="flex items-center gap-1.5 text-xs text-slate-500 mb-6">
                <a href="{{ route('home') }}" wire:navigate class="hover:text-yellow-300 transition-colors">Home</a>
                <svg class="w-3 h-3 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                <span class="text-slate-300 font-medium">Drop Price Archive</span>
            </nav>

            <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-yellow-400 mb-3">
                <span class="text-base leading-none">🌡️</span> Drop Price
            </span>
            <h1 class="text-4xl md:text-5xl font-extrabold leading-tight max-w-2xl">Archive</h1>
            <p class="mt-4 text-slate-400 text-base md:text-lg leading-relaxed max-w-xl">Replay every past puzzle — no spoilers, no time pressure.</p>

            <a href="{{ route('home') }}" wire:navigate
                class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-yellow-400 hover:text-yellow-300 transition-colors">
                Play today's drop →
            </a>
        </div>
    </x-drop-price.band>

    <div class="max-w-6xl mx-auto px-4 py-12">
        @if($puzzles->total() === 0)
            <div class="flex flex-col items-center justify-center py-24 text-center">
                <p class="text-lg font-bold text-gray-900">No past drops yet</p>
                <p class="text-sm text-gray-400 mt-1 mb-6">Play today's puzzle and check back tomorrow — every solved drop joins the archive.</p>
                <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-amber-600 hover:text-amber-700 font-semibold transition-colors">← Play today's drop</a>
            </div>
        @else
            <div x-data="dropPriceArchiveGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5">
                @foreach($puzzles as $p)
                    <a href="{{ route('drop-price.show', $p['puzzle_number']) }}" wire:navigate
                        data-puzzle-number="{{ $p['puzzle_number'] }}"
                        class="group relative bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300 flex flex-col">
                        <span data-played-badge hidden
                            class="absolute top-2 right-2 z-10 text-[10px] font-bold px-2 py-0.5 rounded-full bg-white/90 ring-1 ring-gray-200 text-gray-600 backdrop-blur-sm"></span>
                        <div class="aspect-square bg-slate-900 flex items-center justify-center p-5 overflow-hidden">
                            @if($p['product_image_url'])
                                <x-responsive-image :src="$p['product_image_url']" :alt="$p['product_name']"
                                    loading="lazy" width="240" height="240" sizes="(min-width: 1024px) 220px, (min-width: 640px) 200px, 45vw"
                                    class="max-h-full max-w-full object-contain transition-transform duration-500 group-hover:scale-105" />
                            @else
                                <span class="text-4xl opacity-30 select-none">🌡️</span>
                            @endif
                        </div>
                        <div class="p-4">
                            <p class="text-[11px] font-bold tabular-nums text-gray-400">#{{ $p['puzzle_number'] }} · {{ $p['date'] }}</p>
                            <h2 class="mt-1 text-sm font-bold text-gray-900 group-hover:text-amber-600 transition-colors leading-snug line-clamp-2">{{ $p['product_name'] }}</h2>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $puzzles->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
