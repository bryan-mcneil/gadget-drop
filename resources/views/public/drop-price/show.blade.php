@extends('layouts.public')

@section('content')
    {{-- Same dark heat-band shell as the homepage game section — the archive
         replay should feel like the same game, not a different page. --}}
    <x-drop-price.band>
        <div class="relative max-w-6xl mx-auto px-4 pt-8 md:pt-10">
            <nav class="flex items-center gap-1.5 text-xs text-slate-500 mb-2">
                <a href="{{ route('home') }}" wire:navigate class="hover:text-yellow-300 transition-colors">Home</a>
                <svg class="w-3 h-3 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                <a href="{{ route('drop-price.index') }}" wire:navigate class="hover:text-yellow-300 transition-colors">Drop Price Archive</a>
                <svg class="w-3 h-3 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                <span class="text-slate-300 font-medium">#{{ $puzzle->puzzle_number }}</span>
            </nav>
        </div>

        <div class="relative max-w-6xl mx-auto px-4 pb-10 md:pb-14">
            @livewire('drop-price', [
                'number' => $puzzle->puzzle_number,
                'name' => $puzzle->product_name,
                'image' => $puzzle->product_image_url,
                'puzzleId' => $puzzle->id,
            ])
        </div>
    </x-drop-price.band>
@endsection
