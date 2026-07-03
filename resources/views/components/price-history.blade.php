@props(['stats'])

@php
    // Editorial price-tracking panel — deliberately no CTA (the product card
    // above holds the single affiliate link). Renders nothing without a price.
    $verdictBadge = [
        'lowest'   => ['label' => 'Lowest tracked price', 'classes' => 'bg-green-100 text-green-800 ring-green-200'],
        'good'     => ['label' => 'Below typical price',  'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'typical'  => ['label' => 'Typical price',        'classes' => 'bg-gray-100 text-gray-600 ring-gray-200'],
        'elevated' => ['label' => 'Higher than usual',    'classes' => 'bg-amber-50 text-amber-700 ring-amber-200'],
    ];
@endphp

@if(!empty($stats) && $stats['current'] !== null)
    <div class="border border-gray-200 rounded-xl px-5 py-4 bg-gray-50/60">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex items-center gap-2 text-xs font-bold text-gray-500 uppercase tracking-wider">
                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" /></svg>
                Tracked price
            </div>
            @if($stats['verdict'] !== null && isset($verdictBadge[$stats['verdict']]))
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full ring-1 ring-inset {{ $verdictBadge[$stats['verdict']]['classes'] }}">
                    {{ $verdictBadge[$stats['verdict']]['label'] }}
                </span>
            @endif
        </div>

        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-2xl font-extrabold text-gray-900 tabular-nums">${{ number_format($stats['current'], 2) }}</p>
                @if($stats['checked_at'])
                    <p class="text-xs text-gray-500 mt-0.5">
                        Price checked {{ \Illuminate\Support\Carbon::parse($stats['checked_at'])->diffForHumans() }} — confirm the final price at checkout.
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
                @if($stats['drop_pct'] !== null && $stats['drop_pct'] >= 1)
                    <span class="text-emerald-700 font-semibold">{{ $stats['drop_pct'] }}% below typical</span>
                @endif
            </div>
        @elseif($stats['tracking_since'])
            <p class="mt-2 text-xs text-gray-400">
                We started tracking this price {{ \Illuminate\Support\Carbon::parse($stats['tracking_since'])->diffForHumans() }} — trend stats appear once there's enough history to be honest about.
            </p>
        @endif
    </div>
@endif
