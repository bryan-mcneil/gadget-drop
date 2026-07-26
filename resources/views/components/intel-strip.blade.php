@props(['stats' => null])

{{-- One-line price-truth summary for the dark post header band: current
     tracked price, a 90-day mini sparkline, the verdict chip, and when it
     was last checked. It is a SUMMARY, not a CTA (no link, no buy button) —
     the single affiliate CTA is the product card in the end-zone, so the
     single-CTA rule holds. The full <x-price-history> panel repeats this
     with the window stats further down.

     Renders nothing unless the product has a tracked price: on tips, news,
     and priceless products $stats is null and the band simply has no strip.
     The sparkline + verdict only appear once PriceIntel's honesty gates open
     (≥2 snapshots spanning ≥14 days); before then it is price + checked-at. --}}

@if(!empty($stats) && ($stats['current'] ?? null) !== null)
    <div data-intel-strip class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2.5">
        {{-- Current tracked price --}}
        <span class="inline-flex items-baseline gap-1.5">
            <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-400">Tracked</span>
            <span class="text-xl font-extrabold text-white tabular-nums leading-none">${{ number_format($stats['current'], 2) }}</span>
        </span>

        {{-- 90-day sparkline — strokes itself in on first viewport entry.
             SSR renders it fully drawn; sparklineDraw only animates it. --}}
        @if(!empty($stats['has_stats']) && count($stats['points']) > 1)
            <span x-data="sparklineDraw" class="inline-flex items-center" aria-hidden="true">
                <svg viewBox="0 0 120 28" width="120" height="28" class="text-indigo-400 shrink-0">
                    <title>90-day price trend</title>
                    <polyline x-ref="line" points="{{ \App\Support\PriceIntel::sparklinePoints($stats['points'], 120, 28) }}"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        @endif

        {{-- Verdict chip (null until gates open) --}}
        @if(!empty($stats['verdict']))
            <x-verdict-badge :verdict="$stats['verdict']" :drop-pct="$stats['drop_pct']" size="sm" />
        @endif

        {{-- Last checked --}}
        @if(!empty($stats['checked_at']))
            <span class="text-xs text-gray-400">checked {{ \Illuminate\Support\Carbon::parse($stats['checked_at'])->diffForHumans() }}</span>
        @endif
    </div>
@endif
