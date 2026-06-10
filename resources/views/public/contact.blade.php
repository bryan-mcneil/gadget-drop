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
        {{-- Main contact card --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-8 text-center space-y-4">
            <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">Email us</h2>
            <p class="text-gray-500 text-sm">The fastest way to reach us. We aim to reply within 2 business days.</p>
            <a href="mailto:hello@gadgetdrop.tech"
                class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-8 py-3 rounded-xl transition-colors text-sm">
                hello@gadgetdrop.tech
            </a>
        </div>

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
