@php
    // Ordinal band → emoji + label + (dark-panel) pill colour. Written as literal
    // class strings so the Tailwind purge keeps them — they're referenced only here.
    $bandMeta = [
        'freezing' => ['emoji' => '🥶', 'label' => 'Freezing',  'class' => 'text-sky-300 bg-sky-500/10 ring-sky-500/20'],
        'warm'     => ['emoji' => '😊', 'label' => 'Warm',      'class' => 'text-amber-300 bg-amber-500/10 ring-amber-500/20'],
        'hot'      => ['emoji' => '🔥', 'label' => 'Hot',       'class' => 'text-rose-300 bg-rose-500/10 ring-rose-500/20'],
        'nailed'   => ['emoji' => '🎯', 'label' => 'Nailed it', 'class' => 'text-emerald-300 bg-emerald-500/10 ring-emerald-500/20'],
    ];

    $remaining = \App\Support\DropPrice::MAX_GUESSES - count($results);

    // ── The mercury heat column (the signature) ──────────────────────────
    // Fill height + colour track the *hottest band reached so far*, computed
    // server-side from the ordinal $results — it carries no price/distance, so
    // it stays inside the secrecy boundary. Each guess re-renders the component,
    // so the column rises with every guess.
    $rank = ['freezing' => 1, 'warm' => 2, 'hot' => 3, 'nailed' => 4];
    $hottest = 0;
    foreach ($results as $r) {
        $hottest = max($hottest, $rank[$r['band']] ?? 0);
    }
    $heatPct   = [0 => 8,  1 => 28, 2 => 56, 3 => 82, 4 => 100][$hottest];
    $heatColor = [0 => '#475569', 1 => '#38BDF8', 2 => '#FB923C', 3 => '#FF4D4D', 4 => '#FACC15'][$hottest];
    $heatGlow  = [0 => 'rgba(71,85,105,.25)', 1 => 'rgba(56,189,248,.45)', 2 => 'rgba(251,146,60,.5)', 3 => 'rgba(255,77,77,.55)', 4 => 'rgba(250,204,21,.55)'][$hottest];
    // Below the first guess the mercury sits cold-neutral; after that it's a
    // cold→hottest gradient so the climb reads as "heating up".
    $heatFill = $hottest === 0 ? '#334155' : "linear-gradient(to top, #38BDF8, {$heatColor})";
@endphp

{{-- The Alpine `dropPrice` layer (app.js) owns the client state: localStorage
     streak, one-play-per-day lockout, and the spoiler-free share. It learns the
     round is over from the server-dispatched `dropprice-finished` event — which
     carries ordinal data only, never the price. --}}
<div
    x-data="dropPrice({ number: {{ $puzzleNumber }}, max: {{ \App\Support\DropPrice::MAX_GUESSES }}, shareUrl: @js(url('/')) })"
    @dropprice-finished.window="onFinished($event.detail)"
    class="flex flex-col h-full rounded-2xl overflow-hidden shadow-2xl ring-1 ring-white/10 text-slate-100"
    style="background:#0E1326"
>
    {{-- Heat spectrum accent — the game's signature colour run, clipped by the card. --}}
    <div class="h-1 w-full flex-none" style="background:linear-gradient(90deg,#38BDF8,#FB923C,#FF4D4D,#FACC15)"></div>

    {{-- Header --}}
    <div class="px-5 pt-4 pb-3 flex-none">
        <div class="flex items-center justify-between">
            <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-yellow-400">
                <span class="text-base leading-none">🌡️</span> Drop Price
            </span>
            <span class="text-sm font-bold tabular-nums text-slate-500">#{{ $puzzleNumber }}</span>
        </div>
        <p class="mt-2 text-sm text-slate-400">Guess today's price in {{ \App\Support\DropPrice::MAX_GUESSES }} tries.</p>
    </div>

    {{-- Product --}}
    <div class="px-5 pb-3 flex items-center gap-3 flex-none">
        <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-white/5 ring-1 ring-white/10 flex items-center justify-center overflow-hidden">
            <x-responsive-image :src="$productImage" :alt="$productName" sizes="44px" width="44" height="44"
                class="max-w-full max-h-full object-contain" />
        </div>
        <p class="min-w-0 text-[15px] font-semibold leading-snug">{{ $productName }}</p>
    </div>

    @if(! $finished)
        {{-- ── Live play: the heat column + guesses + input ───────────────── --}}
        <div x-show="!alreadyPlayed" class="flex gap-4 px-5 pb-4 flex-1">
            {{-- Mercury heat column (server-computed; rises/heats with each guess).
                 A faint full-scale heat ghost keeps it reading as a gauge even
                 before the first guess; the solid fill is the live level. --}}
            <div class="relative w-3.5 flex-none self-stretch mb-5 rounded-full bg-white/[0.06] ring-1 ring-white/[0.06]">
                <div class="absolute inset-0 rounded-full opacity-[0.13]" style="background:linear-gradient(to top,#38BDF8,#FB923C,#FF4D4D,#FACC15)"></div>
                <div class="absolute inset-x-0 bottom-0 rounded-full transition-[height] duration-500 ease-out motion-reduce:transition-none"
                    style="height:{{ $heatPct }}%; background:{{ $heatFill }}; box-shadow:0 0 14px {{ $heatGlow }}"></div>
                <div class="absolute left-1/2 -bottom-2 -translate-x-1/2 w-6 h-6 rounded-full ring-1 ring-white/15 transition-colors duration-500 motion-reduce:transition-none"
                    style="background:{{ $heatColor }}; box-shadow:0 0 16px {{ $heatGlow }}"></div>
            </div>

            {{-- Guesses + input --}}
            <div class="flex-1 flex flex-col min-w-0">
                @if(count($results) === 0)
                    {{-- Empty state — invites the first guess and teaches the tell. --}}
                    <div class="flex-1 flex items-center justify-center text-center">
                        <p class="text-sm leading-relaxed text-slate-400 max-w-[13rem]">
                            Type a price and hit Guess.<br>
                            <span class="text-slate-500">I'll tell you how </span><span class="text-sky-300">cold</span><span class="text-slate-500"> or </span><span class="text-rose-300">hot</span><span class="text-slate-500"> you are.</span>
                        </p>
                    </div>
                @else
                    <ul class="space-y-2 mb-3">
                        @foreach($results as $r)
                            @php $meta = $bandMeta[$r['band']]; @endphp
                            <li class="flex items-center justify-between rounded-xl bg-white/[0.035] px-3 py-2">
                                <span class="flex items-baseline gap-2 min-w-0">
                                    <span class="text-lg font-bold tabular-nums">${{ number_format($r['guess']) }}</span>
                                    @if($r['direction'] === 'higher')
                                        <span class="text-xs font-medium text-slate-400 whitespace-nowrap">↑ aim higher</span>
                                    @elseif($r['direction'] === 'lower')
                                        <span class="text-xs font-medium text-slate-400 whitespace-nowrap">↓ aim lower</span>
                                    @endif
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 whitespace-nowrap {{ $meta['class'] }}">
                                    {{ $meta['emoji'] }} {{ $meta['label'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-auto">
                    <form wire:submit="submitGuess" class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 font-bold">$</span>
                            <input type="number" wire:model="guess" min="1" max="100000" step="1" inputmode="numeric" required
                                placeholder="your guess"
                                class="no-spinner w-full pl-7 pr-3 py-3 rounded-xl text-[15px] font-bold tabular-nums text-slate-100 bg-[#080c1a] ring-1 ring-white/10 placeholder:font-normal placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="submitGuess"
                            class="flex-shrink-0 px-5 py-3 bg-yellow-400 text-yellow-950 font-bold text-sm uppercase tracking-wide rounded-xl hover:bg-yellow-300 transition-colors disabled:opacity-60 whitespace-nowrap">
                            <span wire:loading.remove wire:target="submitGuess">Guess</span>
                            <span wire:loading wire:target="submitGuess">…</span>
                        </button>
                    </form>
                    <div class="mt-3 flex items-center gap-1.5">
                        @for($i = 0; $i < \App\Support\DropPrice::MAX_GUESSES; $i++)
                            <span @class([
                                'h-1.5 flex-1 rounded-full',
                                'bg-gradient-to-r from-amber-400 to-rose-500' => $i < count($results),
                                'bg-white/10' => $i >= count($results),
                            ])></span>
                        @endfor
                        <span class="ml-2 text-xs font-medium text-slate-500 whitespace-nowrap">{{ $remaining }} left</span>
                    </div>
                    @error('guess') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- ── Lockout: already played today, restored from localStorage (no price). ── --}}
        <div x-show="alreadyPlayed" x-cloak class="flex-1 flex flex-col items-center justify-center text-center px-5 pb-5">
            <p class="text-2xl font-extrabold" :class="won ? 'text-yellow-400' : 'text-slate-100'"
                x-text="won ? '🎯 Solved it!' : 'Played today'"></p>
            <p class="mt-1 text-sm text-slate-400">You've already played today's drop.</p>
            <p class="mt-4 text-2xl tracking-[0.3em]" x-text="emojiRows"></p>
            <p class="mt-5 text-xs text-slate-500">Next drop: #<span x-text="number + 1"></span> at midnight UTC.</p>
        </div>
    @else
        {{-- ── Reveal: the FIRST point the price enters the DOM ───────────── --}}
        <div class="flex-1 flex flex-col justify-center text-center px-5 pb-5"
            style="{{ $won ? 'background:radial-gradient(120% 80% at 50% 0%, rgba(250,204,21,.14), transparent 60%)' : '' }}">
            @if($won)
                <p class="text-2xl font-extrabold text-yellow-400">🎯 Nailed it</p>
                <p class="mt-1 text-sm text-slate-400">You read the market in {{ count($results) }} {{ \Illuminate\Support\Str::plural('try', count($results)) }}.</p>
            @else
                <p class="text-2xl font-extrabold text-slate-100">So close</p>
                <p class="mt-1 text-sm text-slate-400">Better luck on tomorrow's drop.</p>
            @endif

            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">The drop was</p>
            <p class="mt-1 text-5xl font-extrabold tabular-nums">${{ number_format($revealPrice) }}</p>

            @if($affiliateProductId)
                <a href="{{ route('affiliate.redirect', $affiliateProductId) }}" target="_blank" rel="nofollow sponsored"
                    class="mt-5 inline-flex items-center justify-center gap-2 w-full px-5 py-3.5 bg-orange-500 text-orange-950 font-bold text-sm rounded-xl hover:bg-orange-400 transition-colors shadow-lg">
                    See it on Amazon →
                </a>
                <div class="mt-2.5 text-[11px] text-slate-500">
                    <x-affiliate-disclosure />
                </div>
            @endif
        </div>
    @endif

    {{-- ── Footer: share + save streak (Alpine-driven; shown once finished). ── --}}
    <div class="px-5 pb-5 flex-none">
        <div x-show="finished" x-cloak>
            <button type="button" x-on:click="share()"
                class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-white/[0.06] text-slate-100 font-semibold text-sm rounded-xl hover:bg-white/10 transition-colors ring-1 ring-white/10">
                <span x-show="!copied">📋 Share your result</span>
                <span x-show="copied" x-cloak>Copied to clipboard ✓</span>
            </button>
        </div>

        {{-- Save your streak (= newsletter subscribe). Shown once finished AND
             worth saving (a win, or a 2+ day streak). The submit passes the
             localStorage counters straight into the server save() action. --}}
        <div x-show="finished && showSavePrompt" x-cloak class="mt-4 pt-4 border-t border-white/10">
            @if($saveStatus === 'success')
                <p class="text-center text-sm font-bold text-emerald-400">✓ Streak saved — watch your inbox.</p>
            @elseif($saveStatus === 'duplicate')
                <p class="text-center text-sm font-semibold text-slate-300">You're already on the list — streak synced.</p>
            @else
                <p class="text-center text-sm font-bold text-slate-100">Save your streak</p>
                <p class="text-center text-xs text-slate-400 mb-3">Get tomorrow's drop in your inbox — never lose your stats.</p>
                <form x-on:submit.prevent="$wire.save(stats)" class="flex gap-2">
                    <input type="email" wire:model="email" placeholder="you@email.com" required
                        class="flex-1 px-3 py-2.5 rounded-xl text-sm text-slate-100 bg-[#080c1a] ring-1 ring-white/10 placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                    <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="flex-shrink-0 px-4 py-2.5 bg-yellow-400 text-yellow-950 font-bold text-sm rounded-xl hover:bg-yellow-300 transition-colors disabled:opacity-60 whitespace-nowrap">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">…</span>
                    </button>
                </form>
                @error('email') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
                @if($saveStatus === 'error') <p class="mt-2 text-sm text-rose-400">Something went wrong. Please try again.</p> @endif
            @endif
        </div>
    </div>
</div>
