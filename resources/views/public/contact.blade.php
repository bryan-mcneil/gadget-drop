@extends('layouts.public')

@section('content')
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-3xl mx-auto px-4 py-16 text-center">
            <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-3">Get in touch</p>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight mb-4">Contact GadgetDrop</h1>
            <p class="text-gray-500 max-w-md mx-auto">
                We read every message. Whether it's a correction, a partnership idea, or just a great tip, drop us a line.
            </p>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-14 space-y-8">
        <div class="space-y-3">
            <p class="text-gray-600 leading-relaxed text-sm">
                Every message here goes straight to {{ config('site.author.name') }} — the person who researches
                and writes the site — not a ticket queue. Corrections get priority: if a spec, price, or claim in an
                article is wrong, it typically gets fixed within a day of a good report (link the article, say what's
                off, and include a source if you have one). Product tips and partnership notes are welcome too.
            </p>
        </div>

        {{-- Contact form --}}
        @livewire('contact-form')

        {{-- Direct email fallback --}}
        <p class="text-center text-sm text-gray-500">
            Prefer plain email?
            <a href="mailto:{{ config('site.author.email') }}" class="text-indigo-600 font-semibold hover:underline">{{ config('site.author.email') }}</a>
        </p>

        {{-- Reason cards --}}
        <div class="grid sm:grid-cols-3 gap-4">
            @foreach([
                ['icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'title' => 'Corrections', 'desc' => 'Spotted something wrong? We take accuracy seriously and will fix it fast.'],
                ['icon' => 'M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 4a2 2 0 100-4 2 2 0 000 4zM3 16a2 2 0 100-4 2 2 0 000 4z', 'title' => 'Partnerships', 'desc' => 'Brand collaborations, sponsored content, and media kit requests.'],
                ['icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'title' => 'Tips & Suggestions', 'desc' => 'Know a product we should cover? A tech issue worth a tip? Tell us.'],
            ] as $card)
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 text-center space-y-2">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center mx-auto">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                        </svg>
                    </div>
                    <p class="font-semibold text-gray-900 text-sm">{{ $card['title'] }}</p>
                    <p class="text-xs text-gray-500 leading-relaxed">{{ $card['desc'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="text-center text-xs text-gray-400">
            For privacy-related requests please see our
            <a href="{{ route('privacy') }}" wire:navigate class="text-indigo-500 hover:underline">Privacy Policy</a>.
        </p>
    </div>
@endsection
