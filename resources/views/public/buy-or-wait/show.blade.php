@extends('layouts.public')

@section('content')
{{-- Verdict hero. The H1 carries the dated question because that is the query
     this page answers, and the answer genuinely changes with the date. --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-4xl mx-auto px-4 py-14">
        <a href="{{ route('buy-or-wait.index') }}" wire:navigate
            class="text-xs font-semibold text-indigo-600 uppercase tracking-widest hover:text-indigo-700">
            &larr; Buy or Wait
        </a>
        <h1 class="mt-3 text-4xl font-extrabold text-gray-900 tracking-tight">{{ $title }}</h1>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-buy-or-wait-chip :verdict="$verdict['verdict']" :confidence="$verdict['confidence']" />
            <span class="text-xs text-gray-500">as of {{ now()->format('F j, Y') }}</span>
        </div>

        <p class="mt-5 text-lg text-gray-700 leading-relaxed">{{ $verdict['sentence'] }}</p>

        @if($cycle->isStale())
            <p class="mt-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800 leading-relaxed">
                <strong>Heads up:</strong> we last re-checked this line's release dates against their source in
                {{ $cycle->verified_at?->format('F Y') ?? 'an unrecorded month' }}. That is longer ago than our
                {{ \App\Models\ReleaseCycle::STALE_AFTER_MONTHS }}-month standard, so the cycle half of this
                verdict is directional until we refresh it.
            </p>
        @endif
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 py-12 space-y-12">
    {{-- The factors. This is the page's spine: a reader should be able to audit
         the verdict rather than trust it. --}}
    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">What this is based on</h2>
        <div class="space-y-3">
            @foreach($verdict['factors'] as $factor)
                <div class="border border-gray-200 rounded-xl px-5 py-4 bg-gray-50/60">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ $factor['label'] }}</p>
                    <p class="mt-1.5 text-gray-700 leading-relaxed">{{ $factor['detail'] }}</p>
                    @if($factor['source_url'] || $factor['as_of'])
                        <p class="mt-2 text-xs text-gray-500">
                            @if($factor['as_of'])
                                Checked {{ \Illuminate\Support\Carbon::parse($factor['as_of'])->format('F j, Y') }}@if($factor['source_url']) &middot; @endif
                            @endif
                            @if($factor['source_url'])
                                {{-- External source: full page load, and nofollow so an
                                     editorial citation is not read as an endorsement link. --}}
                                <a href="{{ $factor['source_url'] }}" target="_blank" rel="nofollow noopener"
                                    class="text-indigo-600 underline hover:text-indigo-700 break-all">source</a>
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Price context, reusing the review-page widget. No CTA of its own: the
         single affiliate link lives on the review this page links to. --}}
    @if($stats)
        <section class="space-y-4">
            <h2 class="text-xl font-bold text-gray-900">What {{ $product->name }} costs right now</h2>
            <p class="text-sm text-gray-600 leading-relaxed">
                The price half of the verdict comes from this product, the model on the
                {{ $cycle->name }} line we track and review. Every number is a price we recorded ourselves.
            </p>
            <x-price-history :stats="$stats" />
            @if($review)
                <p class="text-sm">
                    <a href="{{ route('posts.show', $review->slug) }}" wire:navigate
                        class="font-semibold text-indigo-600 hover:text-indigo-700">Read our {{ $review->title }} &rarr;</a>
                </p>
            @endif
        </section>
    @else
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-gray-900">Price context</h2>
            <p class="text-sm text-gray-600 leading-relaxed">
                We do not yet track a product on this line with enough recorded price history to weigh in,
                so the verdict above rests on release timing alone. That is a smaller claim, and we would
                rather make it than dress up a guess. Our tracked drops live on the
                <a href="{{ route('deals') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700">price drops page</a>.
            </p>
        </section>
    @endif

    @if($relatedReviews->isNotEmpty())
        <section class="space-y-4">
            <h2 class="text-xl font-bold text-gray-900">Related reviews</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach($relatedReviews as $related)
                    <a href="{{ route('posts.show', $related->slug) }}" wire:navigate
                        class="block bg-white border border-gray-200 rounded-xl px-4 py-3 hover:border-indigo-200 hover:shadow-sm transition-all">
                        <span class="font-semibold text-gray-900 leading-snug">{{ $related->title }}</span>
                        @if($related->published_at)
                            <span class="block mt-1 text-xs text-gray-400">{{ $related->published_at->format('M j, Y') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="border-t border-gray-100 pt-8 space-y-3">
        <h2 class="text-lg font-bold text-gray-900">How we decide this</h2>
        <p class="text-sm text-gray-600 leading-relaxed">
            A verdict is the release cycle's position crossed with the price's position, and nothing else.
            We never treat a leak or a supply-chain report as evidence: only shipped history and
            announcements the manufacturer has actually made. The full method, including why some verdicts
            say &ldquo;no strong signal&rdquo;, is on
            <a href="{{ route('how-we-review') }}#buy-or-wait" class="text-indigo-600 underline hover:text-indigo-700">How We Review</a>.
        </p>
        <p class="text-sm text-gray-500 leading-relaxed">
            Cycle data for the {{ $cycle->name }} line was last verified against its source on
            <strong>{{ $cycle->verified_at?->format('F j, Y') ?? 'an unrecorded date' }}</strong>. We re-check
            every line quarterly and after any launch on it.
        </p>
    </section>

    {{-- Honest hook: this genuinely is a manual list today, and says so. --}}
    @livewire('join-the-drop')
</div>
@endsection
