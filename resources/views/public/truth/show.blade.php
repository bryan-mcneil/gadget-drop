@extends('layouts.public')

@section('content')
@php($totals = $report['totals'])
@php($classes = $report['classes'])
@php($headline = $report['headline'])
{{-- Copy quotes the thresholds the report was GENERATED with (stored in the
     artifact), never live config — the page must keep matching its data even
     if config/truth.php is tuned later. --}}
@php($realCut = round((1 - $report['config']['thresholds']['real_deal']) * 100))
@php($worseCut = round(($report['config']['thresholds']['worse'] - 1) * 100))
@php($grades = [
    'real_deal' => ['label' => 'Real deals', 'badge' => 'bg-emerald-100 text-emerald-800', 'bar' => 'bg-emerald-500'],
    'repackaged' => ['label' => 'Repackaged', 'badge' => 'bg-amber-100 text-amber-800', 'bar' => 'bg-amber-400'],
    'worse' => ['label' => 'Worse than before', 'badge' => 'bg-rose-100 text-rose-800', 'bar' => 'bg-rose-500'],
    'unobserved' => ['label' => 'Not observed during event', 'badge' => 'bg-gray-100 text-gray-600', 'bar' => 'bg-gray-300'],
    'insufficient' => ['label' => 'Not enough history', 'badge' => 'bg-gray-100 text-gray-600', 'bar' => 'bg-gray-300'],
])

{{-- Hero — the number is the headline --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 py-14">
        <p class="text-xs font-semibold text-sky-600 uppercase tracking-widest mb-3">Truth Report</p>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-6">{{ $title }}</h1>

        @if($headline['real_deal_pct'] !== null)
            <p class="text-6xl sm:text-7xl font-extrabold text-gray-900 tabular-nums tracking-tight">
                {{ number_format($headline['real_deal_pct'], 1) }}%
            </p>
            <p class="mt-3 text-lg text-gray-700 max-w-2xl leading-relaxed">
                of the <strong>{{ $totals['judged'] }}</strong> deals we could judge were genuinely at least
                {{ $realCut }}% below the product's lowest price in the {{ $report['baseline_days'] }} days
                before the event.
            </p>
        @else
            <p class="text-lg text-gray-700 max-w-2xl leading-relaxed">
                We tracked <strong>{{ $totals['tracked'] }}</strong> products through this event window —
                and none of them cleared the bar for an honest verdict. The ledger below says why,
                product by product. That's the report: we'd rather show you an empty scoreboard than
                a guessed one.
            </p>
        @endif

        <p class="mt-4 text-gray-600 max-w-2xl leading-relaxed">
            Of the {{ $totals['tracked'] }} products we track, {{ $totals['judged'] }} met the bar for
            an honest verdict; {{ $totals['unobserved'] }} we never observed during the event itself,
            and {{ $totals['insufficient'] }} lacked enough pre-event history — we say so rather
            than guess.
            Every number below comes from our own recorded price snapshots — never a list price.
        </p>

        <p class="mt-4 text-xs text-gray-400">
            Event window {{ \Illuminate\Support\Carbon::parse($report['window']['from'])->format('M j') }}–{{ \Illuminate\Support\Carbon::parse($report['window']['to'])->format('M j, Y') }}
            &middot; Report generated {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->format('M j, Y') }}
        </p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-12 space-y-12">

    {{-- Grade breakdown bars --}}
    @if($totals['judged'] > 0)
        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-6">How the deals graded out</h2>
            <div class="space-y-5 max-w-2xl">
                @foreach(['real_deal', 'repackaged', 'worse'] as $grade)
                    @php($count = $classes[$grade]['count'])
                    @php($pct = $classes[$grade]['pct'] ?? 0)
                    <div>
                        <div class="flex items-baseline justify-between gap-3 mb-1.5">
                            <span class="text-sm font-semibold text-gray-900">{{ $grades[$grade]['label'] }}</span>
                            <span class="text-sm text-gray-500 tabular-nums">{{ $count }} of {{ $totals['judged'] }} &middot; {{ number_format($pct, 1) }}%</span>
                        </div>
                        <div class="h-3 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $grades[$grade]['bar'] }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($headline['median_discount_pct'] !== null)
                <p class="mt-5 text-sm text-gray-600 max-w-2xl">
                    Median change across everything we judged:
                    <strong class="tabular-nums">{{ $headline['median_discount_pct'] >= 0 ? '−' : '+' }}{{ number_format(abs($headline['median_discount_pct']), 1) }}%</strong>
                    vs each product's pre-event low.
                </p>
            @endif
        </section>
    @endif

    {{-- Best / worst callouts --}}
    @if($headline['biggest_real_deal'] !== null || $headline['biggest_markup'] !== null)
        <section class="grid sm:grid-cols-2 gap-6">
            @if($headline['biggest_real_deal'] !== null)
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-6">
                    <p class="text-xs font-semibold text-emerald-700 uppercase tracking-widest mb-2">Biggest real deal</p>
                    <p class="font-bold text-gray-900 leading-snug">
                        @if($headline['biggest_real_deal']['post_slug'])
                            <a href="{{ route('posts.show', $headline['biggest_real_deal']['post_slug']) }}" wire:navigate class="hover:text-emerald-700 transition-colors">{{ $headline['biggest_real_deal']['product'] }}</a>
                        @else
                            {{ $headline['biggest_real_deal']['product'] }}
                        @endif
                    </p>
                    <p class="mt-1 text-2xl font-extrabold text-emerald-700 tabular-nums">−{{ number_format($headline['biggest_real_deal']['discount_pct'], 1) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">vs its pre-event {{ $report['baseline_days'] }}-day low</p>
                </div>
            @endif
            @if($headline['biggest_markup'] !== null)
                <div class="bg-rose-50 border border-rose-100 rounded-2xl p-6">
                    <p class="text-xs font-semibold text-rose-700 uppercase tracking-widest mb-2">Biggest markup</p>
                    <p class="font-bold text-gray-900 leading-snug">
                        @if($headline['biggest_markup']['post_slug'])
                            <a href="{{ route('posts.show', $headline['biggest_markup']['post_slug']) }}" wire:navigate class="hover:text-rose-700 transition-colors">{{ $headline['biggest_markup']['product'] }}</a>
                        @else
                            {{ $headline['biggest_markup']['product'] }}
                        @endif
                    </p>
                    <p class="mt-1 text-2xl font-extrabold text-rose-700 tabular-nums">+{{ number_format($headline['biggest_markup']['markup_pct'], 1) }}%</p>
                    <p class="text-xs text-gray-500 mt-1">vs its pre-event {{ $report['baseline_days'] }}-day low</p>
                </div>
            @endif
        </section>
    @endif

    {{-- Every product, graded --}}
    <section>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Every product we tracked</h2>
        <p class="text-sm text-gray-500 mb-6 max-w-2xl">
            Product names link to our reviews. There are no store links on this page — grading deals
            and selling them don't belong on the same screen.
        </p>
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wider border-b border-gray-200">
                        <th class="px-4 py-3 font-semibold">Product</th>
                        <th class="px-4 py-3 font-semibold">Grade</th>
                        <th class="px-4 py-3 font-semibold text-right">Pre-event low</th>
                        <th class="px-4 py-3 font-semibold text-right">Event low</th>
                        <th class="px-4 py-3 font-semibold text-right">Change</th>
                        <th class="px-4 py-3 font-semibold text-right">Tracking since</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($report['products'] as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                @if($row['post_slug'])
                                    <a href="{{ route('posts.show', $row['post_slug']) }}" wire:navigate class="hover:text-indigo-600 transition-colors">{{ $row['name'] }}</a>
                                @else
                                    {{ $row['name'] }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block text-[11px] font-bold px-2 py-1 rounded-full whitespace-nowrap {{ $grades[$row['classification']]['badge'] }}">{{ $grades[$row['classification']]['label'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                {{ $row['pre_min'] !== null ? '$'.number_format($row['pre_min'], 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                {{ $row['event_min'] !== null ? '$'.number_format($row['event_min'], 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold {{ $row['discount_pct'] === null ? 'text-gray-400' : ($row['discount_pct'] > 0 ? 'text-emerald-700' : ($row['discount_pct'] < 0 ? 'text-rose-700' : 'text-gray-500')) }}">
                                @if($row['discount_pct'] === null)
                                    —
                                @elseif($row['discount_pct'] > 0)
                                    −{{ number_format($row['discount_pct'], 1) }}%
                                @elseif($row['discount_pct'] < 0)
                                    +{{ number_format(abs($row['discount_pct']), 1) }}%
                                @else
                                    0%
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500 whitespace-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($row['tracked_since'])->format('M j, Y') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Methodology --}}
    <section class="bg-indigo-50 border border-indigo-100 rounded-2xl p-6 sm:p-8 max-w-3xl">
        <h2 class="text-lg font-bold text-gray-900 mb-3">How we grade a deal</h2>
        <p class="text-sm text-gray-600 leading-relaxed">
            A product's lowest price during the event is compared against its lowest recorded price in the
            {{ $report['baseline_days'] }} days before it — from our own snapshot history, never a list price.
            <strong>Real deal</strong> means at least {{ $realCut }}% below that pre-event low.
            Within ±{{ $worseCut }}%, the "sale price" was just&hellip; the price — <strong>repackaged</strong>.
            Above it, the event price was <strong>worse</strong>. Products with less than
            {{ $report['config']['min_baseline_days'] }} days of pre-event history are reported as unjudged,
            never guessed — and a product whose price we never actually recorded during the event window
            is reported as not observed, not graded from stale data. We grade deals, not the retailer —
            a repackaged deal is a marketing decision somewhere upstream, and the same product is often
            a genuine bargain a month later.
        </p>
        {{-- Plain link (no wire:navigate): it targets a #fragment, and the
             browser's native navigation is what reliably scrolls to it. --}}
        <a href="{{ route('how-we-review') }}#deal-verdicts" class="inline-block mt-4 text-sm font-semibold text-indigo-600 hover:text-indigo-700">
            How our price data works &rarr;
        </a>
    </section>

    <p class="text-center text-xs text-gray-400 max-w-xl mx-auto">
        This page contains no affiliate links. Where a product name links anywhere, it links to our review
        — that's where our take (and the single store button) lives.
    </p>
</div>
@endsection
