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
            never a manufacturer's suggested price. Every card shows when we last checked; prices move fast,
            so confirm the final number at checkout. The tracking method is documented on
            <a href="{{ route('how-we-review') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700">How We Review</a>.
        </p>
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

                        <div class="flex items-baseline gap-2 tabular-nums">
                            <span class="text-2xl font-extrabold text-gray-900">${{ number_format($deal['current'], 2) }}</span>
                            <span class="text-sm text-gray-400">usually ${{ number_format($deal['typical'], 2) }}</span>
                        </div>

                        @if(count($deal['points']) > 1)
                            <svg viewBox="0 0 240 48" class="w-full h-10 text-sky-500" preserveAspectRatio="none" aria-hidden="true">
                                <polyline points="{{ \App\Support\PriceIntel::sparklinePoints($deal['points']) }}"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        @endif

                        <p class="text-xs text-gray-400">
                            @if($deal['verdict'] === 'lowest')
                                <span class="text-sky-700 font-semibold">Lowest price we've tracked.</span>
                            @endif
                            Checked {{ \Illuminate\Support\Carbon::parse($deal['checked_at'])->diffForHumans() }}
                        </p>

                        {{-- Read-only worth-it social proof (null until the ≥5-vote gate;
                             ?? guards the 1h window where a pre-deploy cached feed lacks the key). --}}
                        @if(($deal['worth_pct'] ?? null) !== null)
                            <p class="text-xs font-semibold text-emerald-600">{{ $deal['worth_pct'] }}% of {{ $deal['worth_total'] }} readers say worth it</p>
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
