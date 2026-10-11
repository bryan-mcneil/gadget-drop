@extends('layouts.public')

@section('content')
<div class="max-w-xl mx-auto px-4 py-20 text-center">
    @if($state === 'unsubscribed')
        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-5">
            <svg class="w-7 h-7 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </div>
        <h1 class="text-2xl font-extrabold text-gray-900">Watch removed</h1>
        <p class="mt-3 text-sm text-gray-500 leading-relaxed">
            We've stopped watching that price and deleted your email from this watch. No more emails about it, ever.
        </p>
    @else
        <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-5">
            <svg class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
        </div>
        <h1 class="text-2xl font-extrabold text-gray-900">
            {{ $state === 'already-verified' ? "You're already set" : "You're set" }}
        </h1>
        <p class="mt-3 text-sm text-gray-500 leading-relaxed">
            We're watching the price on <strong class="text-gray-700">{{ $watch?->product?->name }}</strong>
            until <strong class="text-gray-700">{{ $watch?->expires_at?->format('M j, Y') }}</strong>.
            If it drops enough below our tracked price from your purchase date, you'll get one email. That's the whole deal.
        </p>
    @endif

    <a href="{{ route('home') }}" wire:navigate class="inline-block mt-8 text-sm font-semibold text-indigo-600 hover:text-indigo-700">
        ← Back to GadgetDrop
    </a>
</div>
@endsection
