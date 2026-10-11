@extends('layouts.public')

@section('content')
<div class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 py-14">
        <p class="text-xs font-semibold text-sky-600 uppercase tracking-widest mb-3">Truth Reports</p>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
            Were the deals actually deals?
        </h1>
        <p class="text-gray-600 leading-relaxed max-w-2xl">
            After every big sale event we grade each product we track against its own recorded pre-event
            price history (real deal, repackaged, or worse) and publish the full per-product data.
            No list prices, no guesses, and no store links on the report pages.
        </p>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 py-12 space-y-4">
    @foreach($reports as $reportSlug => $entry)
        <a href="{{ route('truth.show', $reportSlug) }}" wire:navigate
            class="block bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:border-indigo-200 transition-all">
            <span class="font-bold text-gray-900">{{ $entry['title'] }}</span>
            <span class="block mt-1 text-sm font-semibold text-indigo-600">Read the report &rarr;</span>
        </a>
    @endforeach
</div>
@endsection
