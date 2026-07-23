@extends('layouts.public')

@section('content')
{{-- Hero --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 py-14">
        <p class="text-xs font-semibold text-indigo-600 uppercase tracking-widest mb-3">Buy or Wait</p>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
            Is now a good time to buy?
        </h1>
        <p class="text-gray-600 leading-relaxed max-w-2xl">
            The worst time to buy a gadget is four weeks before its replacement lands. We track when each
            product line has actually refreshed, sourced to manufacturer press releases with the link on
            every page, and cross it with our own recorded price history. Two honest inputs, one
            dated verdict, and no predictions about products nobody has announced.
        </p>
        <p class="mt-4 text-sm text-gray-500 leading-relaxed max-w-2xl">
            {{ $total }} product {{ \Illuminate\Support\Str::plural('line', $total) }} tracked. Every verdict
            below shows its confidence and when we last re-checked the cycle dates against their source.
            {{-- Plain link (no wire:navigate): it targets a #fragment, and native
                 navigation is what reliably scrolls to it. --}}
            <a href="{{ route('how-we-review') }}#buy-or-wait" class="text-indigo-600 underline hover:text-indigo-700">How this works</a>.
        </p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-12 space-y-12">
    @foreach([
        ['rows' => $waiting, 'heading' => 'A refresh is close', 'blurb' => 'Late in the cycle. A successor usually pulls the outgoing model\'s price down with it, so waiting tends to pay twice.'],
        ['rows' => $buying, 'heading' => 'Good time to buy', 'blurb' => 'Early in the cycle and priced on the good side of its own tracked history.'],
        ['rows' => $neutral, 'heading' => 'No strong signal', 'blurb' => 'Mid-cycle, or the price is simply typical. We would rather say so than manufacture urgency.'],
    ] as $group)
        @if(count($group['rows']) > 0)
            <section>
                <h2 class="text-xl font-bold text-gray-900">{{ $group['heading'] }}</h2>
                <p class="mt-1 text-sm text-gray-500 max-w-2xl leading-relaxed">{{ $group['blurb'] }}</p>

                <div class="mt-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($group['rows'] as $row)
                        <a href="{{ route('buy-or-wait.show', $row['slug']) }}" wire:navigate
                            class="block bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:shadow-lg hover:border-indigo-200 transition-all">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-bold text-gray-900 leading-snug">{{ $row['name'] }}</span>
                                <x-buy-or-wait-chip :verdict="$row['verdict']" size="sm" />
                            </div>

                            <p class="mt-3 text-sm text-gray-600 leading-relaxed">
                                {{ $row['last_release_name'] }}:
                                <span class="tabular-nums">{{ $row['months'] }}</span>
                                {{ \Illuminate\Support\Str::plural('month', $row['months']) }} into a
                                <span class="tabular-nums">{{ $row['cadence_months'] }}</span>-month cycle.
                            </p>

                            {{-- Cycle-position bar. Static inline width so there is no
                                 Alpine-driven flash, and it caps at 100% when overdue. --}}
                            <div class="mt-3 h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $row['verdict'] === \App\Support\BuyOrWait::WAIT_FOR_REFRESH ? 'bg-amber-400' : 'bg-indigo-400' }}"
                                    style="width: {{ min(100, max(3, round($row['cycle_position'] * 100))) }}%"></div>
                            </div>

                            <p class="mt-3 text-xs text-gray-400">
                                {{ ucfirst($row['confidence']) }} confidence
                                @if($row['verified_at'])
                                    &middot; cycle data verified {{ \Illuminate\Support\Carbon::parse($row['verified_at'])->format('M Y') }}
                                @endif
                                @if($row['is_stale'])
                                    <span class="text-amber-600 font-semibold">&middot; due a re-check</span>
                                @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    <p class="text-center text-xs text-gray-400 max-w-2xl mx-auto leading-relaxed">
        Release-cycle dates come from manufacturer newsrooms and press releases, linked on every line's page.
        Price commentary uses our own recorded history, never a manufacturer's list price. We do not publish
        rumours or leaks about unannounced products.
    </p>
</div>
@endsection
