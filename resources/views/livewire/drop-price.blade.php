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

    // ── The mercury thermometer (the signature) ──────────────────────────
    // Fill height + colour track the *hottest band reached so far*, computed
    // server-side from the ordinal $results — it carries no price/distance, so
    // it stays inside the secrecy boundary. Each guess re-renders the component,
    // so the column rises with every guess (and hits gold/100% on a win).
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

    // The product-stage aura is a SOLID colour (background-color transitions are
    // animatable; gradients are not) following the hottest band — a cool indigo
    // before the first guess, gold on a win.
    $auraColor = [0 => '#312E81', 1 => '#38BDF8', 2 => '#FB923C', 3 => '#FF4D4D', 4 => '#FACC15'][$hottest];

    // Thermometer scale ticks: each band label sits at the fill level the
    // mercury reaches when that band is hit (mirrors $heatPct).
    $scale = [
        ['pct' => 100, 'label' => 'Nailed',   'class' => 'text-yellow-400/80'],
        ['pct' => 82,  'label' => 'Hot',      'class' => 'text-rose-400/70'],
        ['pct' => 56,  'label' => 'Warm',     'class' => 'text-orange-400/70'],
        ['pct' => 28,  'label' => 'Freezing', 'class' => 'text-sky-400/70'],
    ];
@endphp

{{-- The Alpine `dropPrice` layer (app.js) owns the client state: localStorage
     streak, one-play-per-day lockout, and the spoiler-free share. It learns the
     round is over from the server-dispatched `dropprice-finished` event — which
     carries ordinal data only, never the price. Both bindings stay on this ROOT
     div: Livewire never replaces the root across morphs, which is what keeps the
     Alpine state alive from guess to guess. --}}
<div
    x-data="dropPrice({ number: {{ $puzzleNumber }}, max: {{ \App\Support\DropPrice::MAX_GUESSES }}, shareUrl: @js(url('/')) })"
    @dropprice-finished.window="onFinished($event.detail)"
    class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-12 lg:items-center text-slate-100"
>
    {{-- ── Product stage: the mystery gadget on its heat aura. Persists across
         every state; the aura colour tracks the hottest band reached. ── --}}
    <div class="lg:col-span-5 flex flex-col items-center text-center">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-yellow-400">
                <span class="text-base leading-none">🌡️</span> Drop Price
            </span>
            <span class="text-xs font-bold tabular-nums text-slate-500">#{{ $puzzleNumber }}</span>
        </div>

        <div class="relative mt-8 mb-8">
            <div class="absolute -inset-8 rounded-full blur-3xl opacity-70 transition-colors duration-700 motion-reduce:transition-none animate-[heat-breathe_5s_ease-in-out_infinite] motion-reduce:animate-none"
                style="background-color: {{ $auraColor }}"></div>
            <x-responsive-image :src="$productImage" :alt="$productName" sizes="(min-width: 1024px) 288px, 208px"
                width="288" height="288"
                class="relative w-52 md:w-60 lg:w-72 max-w-full object-contain drop-shadow-2xl" />
        </div>

        <h2 class="text-xl md:text-2xl font-extrabold leading-snug max-w-sm">{{ $productName }}</h2>
        <p class="mt-2 text-sm text-slate-400">Guess today's price in {{ \App\Support\DropPrice::MAX_GUESSES }} tries.</p>
    </div>

    {{-- ── Thermometer + board ── --}}
    <div class="lg:col-span-7 flex gap-4 md:gap-6 min-w-0">
        {{-- Mercury thermometer (server-computed; decorative — the band pills
             carry the same information for assistive tech). --}}
        <div class="flex gap-3 flex-none" aria-hidden="true">
            <div class="relative w-5 mb-7 rounded-full bg-white/[0.06] ring-1 ring-white/[0.06]">
                <div class="absolute inset-0 rounded-full opacity-[0.13]" style="background:linear-gradient(to top,#38BDF8,#FB923C,#FF4D4D,#FACC15)"></div>
                <div class="absolute inset-x-0 bottom-0 rounded-full transition-[height] duration-500 ease-out motion-reduce:transition-none"
                    style="height:{{ $heatPct }}%; background:{{ $heatFill }}; box-shadow:0 0 14px {{ $heatGlow }}"></div>
                <div class="absolute left-1/2 -bottom-3 -translate-x-1/2 w-8 h-8 rounded-full ring-1 ring-white/15 transition-colors duration-500 motion-reduce:transition-none"
                    style="background:{{ $heatColor }}; box-shadow:0 0 18px {{ $heatGlow }}"></div>
            </div>
            {{-- Band scale — where the mercury lands per band; doubles as a
                 how-to-play legend. Hidden on the smallest screens. --}}
            <div class="relative w-16 mb-7 hidden sm:block select-none">
                @foreach($scale as $tick)
                    <span class="absolute left-0 flex items-center gap-1.5 translate-y-1/2" style="bottom:{{ $tick['pct'] }}%">
                        <span class="w-2 h-px bg-white/20"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider {{ $tick['class'] }}">{{ $tick['label'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Persistent board wrapper: the three states swap INSIDE this div so
             the thermometer/stage siblings are never touched by the morph, and
             min-h keeps the band height steady across states. --}}
        <div class="flex-1 min-w-0 flex flex-col min-h-[24rem]">
            @if(! $finished)
                {{-- ── Live play: one fixed slot per allowed guess (Wordle-style,
                     so the board never grows) + the guess form. ── --}}
                <div x-show="!alreadyPlayed" wire:key="dp-live" class="flex-1 flex flex-col">
                    <ul class="space-y-2.5 mb-5">
                        @for($i = 0; $i < \App\Support\DropPrice::MAX_GUESSES; $i++)
                            @if(isset($results[$i]))
                                @php $r = $results[$i]; $meta = $bandMeta[$r['band']]; @endphp
                                <li wire:key="dp-guess-{{ $i }}" class="h-12 rounded-xl bg-white/[0.035] ring-1 ring-white/[0.06]">
                                    {{-- The inner div is a fresh node when the slot fills, so
                                         the entrance animation plays exactly once. --}}
                                    <div class="h-full flex items-center justify-between gap-2 px-4 animate-[guess-in_.35s_ease-out_both] motion-reduce:animate-none">
                                        <span class="flex items-baseline gap-2.5 min-w-0">
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
                                    </div>
                                </li>
                            @else
                                <li wire:key="dp-guess-{{ $i }}" class="h-12 rounded-xl bg-white/[0.02] ring-1 ring-white/[0.04] flex items-center px-4">
                                    @if(count($results) === 0 && $i === 0)
                                        {{-- Empty state — invites the first guess and teaches the tell. --}}
                                        <span class="text-xs sm:text-sm leading-snug text-slate-500">Type a price — I'll tell you how <span class="text-sky-300">cold</span> or <span class="text-rose-300">hot</span> you are.</span>
                                    @else
                                        <span class="text-xs font-bold tabular-nums text-slate-700">{{ $i + 1 }}</span>
                                    @endif
                                </li>
                            @endif
                        @endfor
                    </ul>

                    <div class="mt-auto">
                        <form wire:submit="submitGuess" class="flex gap-2.5">
                            <div class="relative flex-1">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-bold">$</span>
                                <input type="number" wire:model="guess" min="1" max="100000" step="1" inputmode="numeric" required
                                    placeholder="your guess"
                                    class="no-spinner w-full pl-8 pr-3 py-3.5 rounded-xl text-base font-bold tabular-nums text-slate-100 bg-white/[0.04] ring-1 ring-white/10 placeholder:font-normal placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="submitGuess"
                                class="flex-shrink-0 px-6 py-3.5 bg-yellow-400 text-yellow-950 font-bold text-sm uppercase tracking-wide rounded-xl hover:bg-yellow-300 transition-colors disabled:opacity-60 whitespace-nowrap">
                                <span wire:loading.remove wire:target="submitGuess">Guess</span>
                                <span wire:loading wire:target="submitGuess">…</span>
                            </button>
                        </form>
                        <div class="mt-3.5 flex items-center gap-1.5">
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

                {{-- ── Lockout: already played today, restored from localStorage (no price). ── --}}
                <div x-show="alreadyPlayed" x-cloak wire:key="dp-lockout" class="flex-1 flex flex-col items-center justify-center text-center">
                    <p class="text-3xl font-extrabold" :class="won ? 'text-yellow-400' : 'text-slate-100'"
                        x-text="won ? '🎯 Solved it!' : 'Played today'"></p>
                    <p class="mt-1.5 text-sm text-slate-400">You've already played today's drop.</p>
                    <p class="mt-5 text-3xl tracking-[0.3em]" x-text="emojiRows"></p>
                    <p class="mt-6 text-xs text-slate-500">Next drop: #<span x-text="number + 1"></span> at midnight UTC.</p>
                </div>
            @else
                {{-- ── Reveal: the FIRST point the price enters the DOM ── --}}
                <div wire:key="dp-reveal" class="flex-1 flex flex-col items-center justify-center text-center">
                    @if($won)
                        <p class="text-3xl font-extrabold text-yellow-400">🎯 Nailed it</p>
                        <p class="mt-1.5 text-sm text-slate-400">You read the market in {{ count($results) }} {{ \Illuminate\Support\Str::plural('try', count($results)) }}.</p>
                    @else
                        <p class="text-3xl font-extrabold text-slate-100">So close</p>
                        <p class="mt-1.5 text-sm text-slate-400">Better luck on tomorrow's drop.</p>
                    @endif

                    <p class="mt-7 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">The drop was</p>
                    {{-- SSR text is the real price (tests assert it); countUp only
                         redraws it as a flourish and bails on reduced motion. --}}
                    <p class="mt-2 text-6xl md:text-7xl font-extrabold tabular-nums {{ $won ? 'text-yellow-400' : 'text-slate-100' }} animate-[reveal-pop_.5s_ease-out_both] motion-reduce:animate-none"
                        data-price="{{ $revealPrice }}" x-init="countUp($el)">${{ number_format($revealPrice) }}</p>

                    @if($affiliateProductId)
                        <a href="{{ route('affiliate.redirect', $affiliateProductId) }}" target="_blank" rel="nofollow sponsored"
                            class="mt-7 inline-flex items-center justify-center gap-2 w-full sm:w-auto px-5 sm:px-8 py-3.5 bg-orange-500 text-orange-950 font-bold text-sm rounded-xl hover:bg-orange-400 transition-colors shadow-lg">
                            See it on Amazon →
                        </a>
                        <div class="mt-2.5 text-[11px] text-slate-500">
                            <x-affiliate-disclosure />
                        </div>
                    @endif
                </div>
            @endif

            {{-- ── Footer: share + save streak (Alpine-driven; shown once finished). ── --}}
            <div class="flex-none">
                <div x-show="finished" x-cloak class="mt-6">
                    <button type="button" x-on:click="share()"
                        class="inline-flex items-center justify-center gap-2 w-full px-4 py-3 bg-white/[0.06] text-slate-100 font-semibold text-sm rounded-xl hover:bg-white/10 transition-colors ring-1 ring-white/10">
                        <span x-show="!copied">📋 Share your result</span>
                        <span x-show="copied" x-cloak>Copied to clipboard ✓</span>
                    </button>
                </div>

                {{-- Save your streak (= newsletter subscribe). Shown once finished AND
                     worth saving (a win, or a 2+ day streak). The submit passes the
                     localStorage counters straight into the server save() action. --}}
                <div x-show="finished && showSavePrompt" x-cloak class="mt-5 pt-5 border-t border-white/10">
                    @if($saveStatus === 'success')
                        <p class="text-center text-sm font-bold text-emerald-400">✓ Streak saved — watch your inbox.</p>
                    @elseif($saveStatus === 'duplicate')
                        <p class="text-center text-sm font-semibold text-slate-300">You're already on the list — streak synced.</p>
                    @else
                        <p class="text-center text-sm font-bold text-slate-100">Save your streak</p>
                        <p class="text-center text-xs text-slate-400 mb-3">Get tomorrow's drop in your inbox — never lose your stats.</p>
                        <form x-on:submit.prevent="$wire.save(stats)" class="flex gap-2">
                            <input type="email" wire:model="email" placeholder="you@email.com" required
                                class="flex-1 min-w-0 px-3 py-2.5 rounded-xl text-sm text-slate-100 bg-white/[0.04] ring-1 ring-white/10 placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-yellow-400" />
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
    </div>
</div>
