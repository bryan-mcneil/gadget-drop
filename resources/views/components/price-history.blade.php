@props(['stats'])

{{-- Editorial price-tracking panel — deliberately no CTA (the product card
     above holds the single affiliate link). Renders nothing without a price.
     The verdict language lives in <x-verdict-badge>. --}}

@if(!empty($stats) && $stats['current'] !== null)
    <div class="border border-gray-200 rounded-xl px-5 py-4 bg-gray-50/60">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex items-center gap-2 text-xs font-bold text-gray-500 uppercase tracking-wider">
                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" /></svg>
                Tracked price
            </div>
            <x-verdict-badge :verdict="$stats['verdict']" :drop-pct="$stats['drop_pct']" />
        </div>

        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-2xl font-extrabold text-gray-900 tabular-nums">${{ number_format($stats['current'], 2) }}</p>
                @if($stats['checked_at'])
                    <p class="text-xs text-gray-500 mt-0.5">
                        Price checked {{ \Illuminate\Support\Carbon::parse($stats['checked_at'])->diffForHumans() }}. Confirm the final price at checkout.
                    </p>
                @endif
            </div>

            @if($stats['has_stats'] && count($stats['points']) > 1)
                <svg viewBox="0 0 240 48" width="240" height="48" aria-hidden="true" class="max-w-full text-indigo-500 shrink-0">
                    <title>Price trend over the last 90 days</title>
                    <polyline points="{{ \App\Support\PriceIntel::sparklinePoints($stats['points']) }}"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            @endif
        </div>

        @if($stats['has_stats'])
            <div class="mt-3 pt-3 border-t border-gray-200 flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-600 tabular-nums">
                <span>90-day low <strong class="text-gray-900">${{ number_format($stats['low90'], 2) }}</strong></span>
                <span>typical <strong class="text-gray-900">${{ number_format($stats['avg90'], 2) }}</strong></span>
                <span>high <strong class="text-gray-900">${{ number_format($stats['high90'], 2) }}</strong></span>
            </div>
            {{-- The Omnibus move: EU law forces retailers to disclose the prior
                 30-day lowest price; US law has no equivalent. We show it anyway. --}}
            <p class="mt-2 text-[11px] text-gray-500" title="The EU requires retailers to disclose this. We do it voluntarily.">
                Lowest price in the last 30 days: <strong class="text-gray-700 tabular-nums">${{ number_format($stats['low30'], 2) }}</strong>
            </p>
        @elseif($stats['tracking_since'])
            <p class="mt-2 text-xs text-gray-400">
                We started tracking this price {{ \Illuminate\Support\Carbon::parse($stats['tracking_since'])->diffForHumans() }}. Trend stats appear once there's enough history.
            </p>
        @endif
    </div>
@endif
