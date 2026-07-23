@extends('layouts.public')

@section('content')
{{-- Hero --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 py-14">
        <p class="text-xs font-semibold text-sky-600 uppercase tracking-widest mb-3">Price Drops</p>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
            Drops we actually tracked
        </h1>
        <p class="text-gray-600 leading-relaxed max-w-2xl">
            Most "was $199" claims are built on list prices nobody ever paid. These aren't. We record the
            real Amazon price of every product we cover each time we check it, and a gadget only lands on
            this page when its current price sits at least {{ \App\Http\Controllers\DealsController::MIN_DROP_PCT }}%
            below what our own history says is typical. "Usually" means <em>our tracked 90-day average</em>,
            never a manufacturer's suggested price. Every card shows the lowest price we've tracked in the
            last 30 days (the disclosure EU law requires of retailers, and US law doesn't) and when we last
            checked; prices move fast, so confirm the final number at checkout. The tracking method is documented on
            {{-- Plain link (no wire:navigate): it targets a #fragment, and the
                 browser's native navigation is what reliably scrolls to it. --}}
            <a href="{{ route('how-we-review') }}#deal-verdicts" class="text-indigo-600 underline hover:text-indigo-700">How We Review</a>.
        </p>
        <p class="mt-4 text-gray-600 leading-relaxed max-w-2xl">
            A low price isn't the whole question, though: buying four weeks before a replacement lands
            costs more than any deal saves. Our
            <a href="{{ route('buy-or-wait.index') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700 font-semibold">buy-or-wait verdicts</a>
            track when each product line has actually refreshed, sourced to the manufacturer's own announcement.
        </p>
        @if($truthPromo)
            <p class="mt-4 text-gray-600 leading-relaxed max-w-2xl">
                Sale event just wrapped? We graded every deal we tracked against its own pre-event price
                history. Read
                <a href="{{ route('truth.show', $truthPromo['slug']) }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700 font-semibold">{{ $truthPromo['title'] }}</a>.
            </p>
        @endif
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-12">
    @if(count($deals) === 0)
        <div class="flex flex-col items-center justify-center py-24 text-center max-w-md mx-auto">
            <span class="text-4xl mb-4">📉</span>
            <p class="text-lg font-bold text-gray-900">No qualifying drops right now</p>
            <p class="text-sm text-gray-500 mt-2 leading-relaxed">
                We only list a product when its price is genuinely {{ \App\Http\Controllers\DealsController::MIN_DROP_PCT }}%+
                below its tracked typical price. No manufactured urgency. Check back soon, or
                <a href="{{ route('home') }}" wire:navigate class="text-indigo-600 font-semibold hover:underline">browse today's picks</a>.
            </p>
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($deals as $deal)
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow flex flex-col">
                    <a href="{{ route('posts.show', $deal['post_slug']) }}" wire:navigate class="block bg-gray-50 p-6 flex items-center justify-center h-44">
                        @if($deal['image_url'])
                            {{-- External Amazon-hosted image (not a storage asset) — raw img is correct here. --}}
                            <img src="{{ $deal['image_url'] }}" alt="{{ $deal['name'] }}" loading="lazy"
                                width="180" height="140" class="max-h-full max-w-full w-auto h-auto object-contain" />
                        @else
                            <span class="text-4xl opacity-30 select-none">📦</span>
                        @endif
                    </a>
                    <div class="p-5 flex flex-col gap-3 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('posts.show', $deal['post_slug']) }}" wire:navigate
                                class="font-bold text-gray-900 leading-snug hover:text-indigo-600 transition-colors line-clamp-2">{{ $deal['name'] }}</a>
                            <span class="shrink-0 text-[11px] font-bold px-2 py-1 rounded-full bg-sky-100 text-sky-800 tabular-nums">−{{ round($deal['drop_pct']) }}%</span>
                        </div>

                        {{-- gap-y-1 (not 0.5): already in the compiled bundle, so the
                             build stays byte-identical and the committed CSS needs no churn. --}}
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 tabular-nums">
                            <span class="text-2xl font-extrabold text-gray-900">${{ number_format($deal['current'], 2) }}</span>
                            <span class="text-sm text-gray-400">usually ${{ number_format($deal['typical'], 2) }}</span>
                            {{-- The Omnibus reference line. ?? guards the 1h window where a
                                 pre-deploy cached feed lacks the key (worth_pct precedent). --}}
                            @if(($deal['low30'] ?? null) !== null)
                                <span class="text-sm text-gray-400">30-day low ${{ number_format($deal['low30'], 2) }}</span>
                            @endif
                        </div>

                        @if(count($deal['points']) > 1)
                            <svg viewBox="0 0 240 48" class="w-full h-10 text-sky-500" preserveAspectRatio="none" aria-hidden="true">
                                <polyline points="{{ \App\Support\PriceIntel::sparklinePoints($deal['points']) }}"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        @endif

                        <p class="text-xs text-gray-400">
                            {{-- Shared verdict language (feed only admits lowest|good, so a badge
                                 always renders). No :drop-pct: the −N% pill above already carries
                                 the magnitude. --}}
                            <x-verdict-badge :verdict="$deal['verdict']" size="sm" />
                            Checked {{ \Illuminate\Support\Carbon::parse($deal['checked_at'])->diffForHumans() }}
                        </p>

                        {{-- Read-only worth-it social proof (null until the ≥5-vote gate;
                             ?? guards the 1h window where a pre-deploy cached feed lacks the key).
                             Keep the "{pct}% of {n} readers say worth it" text run unbroken by tags. --}}
                        @if(($deal['worth_pct'] ?? null) !== null)
                            <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777ZM2.331 10.727a11.969 11.969 0 0 0-.831 4.398 12 12 0 0 0 .52 3.507C2.28 19.482 3.105 20 3.994 20H4.9c.445 0 .72-.498.523-.898a8.963 8.963 0 0 1-.924-3.977c0-1.708.476-3.305 1.302-4.666.245-.403-.028-.959-.5-.959H4.25c-.832 0-1.612.453-1.918 1.227Z" /></svg>
                                {{ $deal['worth_pct'] }}% of {{ $deal['worth_total'] }} readers say worth it
                            </span>
                        @endif

                        <div class="mt-auto pt-2 flex items-center justify-between gap-3">
                            <a href="{{ route('posts.show', $deal['post_slug']) }}" wire:navigate
                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Read our take →</a>
                            {{-- Affiliate redirect: full page load, never wire:navigate --}}
                            <a href="{{ route('affiliate.redirect', ['product' => $deal['product_id'], 'post' => $deal['post_id']]) }}"
                                target="_blank" rel="nofollow sponsored"
                                class="text-xs font-semibold bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg transition-colors">
                                View on Amazon
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-center text-xs text-gray-400 max-w-xl mx-auto">
            GadgetDrop earns a commission on qualifying Amazon purchases at no extra cost to you.
            Drop percentages compare the current price to our tracked 90-day average for the same product.
        </p>
    @endif
</div>
@endsection
