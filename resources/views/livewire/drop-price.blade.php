@php
    // Ordinal band → emoji + colour. Written as literal class strings so the
    // Tailwind purge scan keeps them (band colours are only referenced here).
    $bandMeta = [
        'freezing' => ['emoji' => '🥶', 'label' => 'Freezing', 'class' => 'bg-sky-100 text-sky-700 ring-sky-200'],
        'warm'     => ['emoji' => '😊', 'label' => 'Warm',     'class' => 'bg-amber-100 text-amber-700 ring-amber-200'],
        'hot'      => ['emoji' => '🔥', 'label' => 'Hot',      'class' => 'bg-rose-100 text-rose-700 ring-rose-200'],
        'nailed'   => ['emoji' => '🎯', 'label' => 'Nailed it', 'class' => 'bg-emerald-100 text-emerald-700 ring-emerald-200'],
    ];
    $remaining = \App\Support\DropPrice::MAX_GUESSES - count($results);
@endphp

<div class="flex flex-col h-full rounded-2xl bg-white shadow-xl ring-1 ring-black/5 overflow-hidden">
    {{-- Header --}}
    <div class="px-5 pt-5 pb-4 border-b border-gray-100">
        <div class="flex items-center justify-between">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-indigo-600">
                <span class="text-base leading-none">💰</span> Drop Price
            </span>
            <span class="text-xs font-semibold text-gray-400">#{{ $puzzleNumber }}</span>
        </div>
        <p class="mt-1 text-sm text-gray-500">Guess the Amazon price in {{ \App\Support\DropPrice::MAX_GUESSES }} tries.</p>
    </div>

    {{-- Product --}}
    <div class="px-5 py-4 flex items-center gap-4">
        <div class="flex-shrink-0 w-20 h-20 rounded-xl bg-gray-50 ring-1 ring-gray-100 flex items-center justify-center overflow-hidden">
            <x-responsive-image :src="$productImage" :alt="$productName" sizes="80px" width="80" height="80"
                class="max-w-full max-h-full object-contain" />
        </div>
        <p class="min-w-0 text-base font-bold text-gray-900 leading-snug">{{ $productName }}</p>
    </div>

    {{-- Guess results (ordinal only — never the price) --}}
    @if(count($results) > 0)
        <ul class="px-5 space-y-2">
            @foreach($results as $r)
                @php $meta = $bandMeta[$r['band']]; @endphp
                <li class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2">
                    <span class="flex items-center gap-2 font-semibold text-gray-900">
                        ${{ number_format($r['guess']) }}
                        @if($r['direction'] === 'higher')
                            <span class="text-xs font-medium text-gray-500">↑ aim higher</span>
                        @elseif($r['direction'] === 'lower')
                            <span class="text-xs font-medium text-gray-500">↓ aim lower</span>
                        @endif
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 {{ $meta['class'] }}">
                        {{ $meta['emoji'] }} {{ $meta['label'] }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Input / reveal --}}
    <div class="px-5 py-4 mt-auto">
        @if(! $finished)
            <form wire:submit="guess" class="flex gap-2">
                <div class="relative flex-1">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">$</span>
                    <input type="number" wire:model="guess" min="1" max="100000" step="1" inputmode="numeric" required
                        placeholder="Your guess"
                        class="w-full pl-7 pr-3 py-3 rounded-xl text-sm text-gray-900 bg-gray-50 ring-1 ring-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="guess"
                    class="flex-shrink-0 px-5 py-3 bg-indigo-600 text-white font-bold text-sm rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-60 whitespace-nowrap">
                    <span wire:loading.remove wire:target="guess">Guess</span>
                    <span wire:loading wire:target="guess">…</span>
                </button>
            </form>
            <div class="mt-3 flex items-center gap-1.5">
                @for($i = 0; $i < \App\Support\DropPrice::MAX_GUESSES; $i++)
                    <span @class([
                        'h-1.5 flex-1 rounded-full',
                        'bg-indigo-500' => $i < count($results),
                        'bg-gray-200' => $i >= count($results),
                    ])></span>
                @endfor
                <span class="ml-2 text-xs font-medium text-gray-400 whitespace-nowrap">{{ $remaining }} left</span>
            </div>
            @error('guess') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
        @else
            {{-- Reveal — the FIRST point the price appears in the DOM --}}
            <div class="text-center">
                @if($won)
                    <p class="text-2xl font-extrabold text-emerald-600">🎯 Nailed it!</p>
                    <p class="mt-1 text-sm text-gray-500">You guessed it in {{ count($results) }} {{ \Illuminate\Support\Str::plural('try', count($results)) }}.</p>
                @else
                    <p class="text-2xl font-extrabold text-gray-900">So close!</p>
                    <p class="mt-1 text-sm text-gray-500">Better luck on tomorrow's drop.</p>
                @endif

                <p class="mt-4 text-sm text-gray-500">The price was</p>
                <p class="text-4xl font-extrabold text-gray-900">${{ number_format($revealPrice) }}</p>

                @if($affiliateProductId)
                    <a href="{{ route('affiliate.redirect', $affiliateProductId) }}" target="_blank" rel="nofollow sponsored"
                        class="mt-4 inline-flex items-center justify-center gap-2 w-full px-5 py-3 bg-amber-400 text-gray-900 font-bold text-sm rounded-xl hover:bg-amber-300 transition-colors shadow">
                        See it on Amazon →
                    </a>
                    <div class="mt-3">
                        <x-affiliate-disclosure />
                    </div>
                @endif
            </div>

            {{-- Save your streak (= newsletter subscribe) --}}
            <div class="mt-5 pt-5 border-t border-gray-100">
                @if($saveStatus === 'success')
                    <p class="text-center text-sm font-bold text-emerald-600">✓ Streak saved — watch your inbox.</p>
                @elseif($saveStatus === 'duplicate')
                    <p class="text-center text-sm font-semibold text-gray-600">You're already on the list — streak synced.</p>
                @else
                    <p class="text-center text-sm font-bold text-gray-900">Save your streak</p>
                    <p class="text-center text-xs text-gray-500 mb-3">Get the daily drop &amp; never lose your stats.</p>
                    <form wire:submit="save" class="flex gap-2">
                        <input type="email" wire:model="email" placeholder="your@email.com" required
                            class="flex-1 px-3 py-2.5 rounded-xl text-sm text-gray-900 bg-gray-50 ring-1 ring-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                            class="flex-shrink-0 px-4 py-2.5 bg-indigo-600 text-white font-bold text-sm rounded-xl hover:bg-indigo-700 transition-colors disabled:opacity-60 whitespace-nowrap">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">…</span>
                        </button>
                    </form>
                    @error('email') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    @if($saveStatus === 'error') <p class="mt-2 text-sm text-rose-600">Something went wrong. Please try again.</p> @endif
                @endif
            </div>
        @endif
    </div>
</div>
