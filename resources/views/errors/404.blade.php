@extends('layouts.public')

@php
    view()->share('serverMeta', [
        'title'       => 'Page Not Found | GadgetDrop',
        'description' => null,
        'og_image'    => null,
        'og_type'     => 'website',
        'canonical'   => url()->current(),
    ]);
@endphp

@push('head')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <div class="min-h-[60vh] flex items-center justify-center px-4">
        <div class="text-center max-w-md">
            <p class="text-8xl font-extrabold text-indigo-100 select-none mb-2">404</p>
            <h1 class="text-2xl font-extrabold text-gray-900 mb-3">Page not found</h1>
            <p class="text-gray-500 mb-8 leading-relaxed">
                This page doesn't exist or may have been moved. Try the homepage or search for what you're looking for.
            </p>
            <div class="flex items-center justify-center gap-3 flex-wrap">
                <a href="{{ route('home') }}" wire:navigate class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Back to home</a>
                <a href="{{ route('search') }}" wire:navigate class="border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-xl transition-colors text-sm">Search posts</a>
            </div>
        </div>
    </div>
@endsection
