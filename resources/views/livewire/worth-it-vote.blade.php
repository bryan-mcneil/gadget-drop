@php
    // Static, developer-controlled icon markup. The thumbs share one builder so the
    // long paths live once and can be re-sized (buttons h-5, result chip h-4).
    $thumbUpPath = 'M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904M14 9h2.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z';
    $thumbDownPath = 'M7.5 14.75h2.25m8.024-9.75c.011.05.028.1.052.148.591 1.2.924 2.55.924 3.977a8.96 8.96 0 0 1-.999 4.125m.023-8.25c-.076-.365.183-.75.575-.75h.908c.889 0 1.713.518 1.972 1.368.339 1.11.521 2.287.521 3.507 0 1.553-.295 3.036-.831 4.398C20.613 14.297 19.833 14.75 19 14.75h-1.053c-.472 0-.745-.556-.5-.96a8.95 8.95 0 0 0 .303-.54m.023-8.25H16.48a4.5 4.5 0 0 1-1.423-.23l-3.114-1.04a4.5 4.5 0 0 0-1.423-.23H6.504c-.618 0-1.217.247-1.605.729A11.95 11.95 0 0 0 2.25 11.75c0 .434.023.863.068 1.285.114 1.022 1.032 1.715 2.058 1.715h3.126c.618 0 .991.724.725 1.282A7.471 7.471 0 0 0 7.5 19.25a2.25 2.25 0 0 0 2.25 2.25.75.75 0 0 0 .75-.75v-.633c0-.573.11-1.14.322-1.672.304-.76.93-1.33 1.653-1.715a9.04 9.04 0 0 0 2.86-2.4c.498-.634 1.226-1.08 2.032-1.08h.384';
    $thumb = fn (string $path, string $cls = 'h-5 w-5') => '<svg class="'.$cls.'" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.$path.'" /></svg>';

    $iconChat = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>';
    $iconCheck = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>';
@endphp

<div class="my-10">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 to-white px-6 py-8 text-center shadow-sm">
        @if ($voted === null)
            {{-- Ballot --}}
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                {!! $iconChat !!}
            </div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-emerald-600">Your take</p>
            <p class="mt-1 text-lg font-extrabold text-gray-900">Was this worth it?</p>
            <p class="mx-auto mt-1.5 mb-6 max-w-xs text-xs leading-relaxed text-gray-500">One tap, no account. Your vote is anonymous and helps other readers.</p>

            <div class="mx-auto flex max-w-sm items-stretch justify-center gap-3">
                <button type="button" wire:click="vote('worth')" wire:loading.attr="disabled" wire:target="vote"
                    aria-pressed="false" aria-label="Vote: worth it"
                    class="group inline-flex min-w-0 flex-1 items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-5 py-3 text-sm font-bold text-emerald-700 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50 hover:shadow-md active:translate-y-0 active:shadow-sm disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-sm">
                    {!! $thumb($thumbUpPath, 'h-5 w-5 shrink-0 transition-transform duration-150 group-hover:scale-110') !!}
                    Worth it
                </button>
                <button type="button" wire:click="vote('skip')" wire:loading.attr="disabled" wire:target="vote"
                    aria-pressed="false" aria-label="Vote: I would skip it"
                    class="group inline-flex min-w-0 flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-bold text-gray-600 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-gray-300 hover:bg-gray-50 hover:shadow-md active:translate-y-0 active:shadow-sm disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-sm">
                    {!! $thumb($thumbDownPath, 'h-5 w-5 shrink-0 transition-transform duration-150 group-hover:scale-110') !!}
                    I'd skip
                </button>
            </div>
        @else
            {{-- Result --}}
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 ring-1 ring-emerald-200">
                {!! $iconCheck !!}
            </div>
            <p class="text-lg font-extrabold text-gray-900">Thanks for voting!</p>

            @if ($summary['pct'] !== null)
                <div class="mx-auto mt-4 max-w-xs">
                    <div class="flex items-baseline justify-center gap-1.5">
                        <span class="text-4xl font-black leading-none tabular-nums text-emerald-600">{{ $summary['pct'] }}%</span>
                    </div>
                    <p class="mt-1.5 text-sm text-gray-600">of {{ $summary['total'] }} readers say it's worth it</p>

                    {{-- Two-tone proportion bar: emerald = worth, slate = skip. --}}
                    <div class="mt-4 flex h-2.5 w-full overflow-hidden rounded-full bg-gray-200" role="img"
                        aria-label="{{ $summary['pct'] }}% of {{ $summary['total'] }} readers say worth it">
                        <div class="h-full bg-emerald-500" style="width: {{ $summary['pct'] }}%"></div>
                        <div class="h-full bg-gray-300" style="width: {{ 100 - $summary['pct'] }}%"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-semibold">
                        <span class="text-emerald-600">{{ $summary['worth'] }} worth it</span>
                        <span class="text-gray-400">{{ $summary['skip'] }} would skip</span>
                    </div>
                </div>
            @else
                <p class="mx-auto mt-1.5 max-w-xs text-sm leading-relaxed text-gray-500">
                    Early votes: you're one of the first {{ $summary['total'] }}. Check back as more readers weigh in.
                </p>
            @endif

            {{-- The visitor's own choice, shown as a quiet confirmation chip. --}}
            <div class="mt-5 inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold {{ $voted === 'worth' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-50 text-gray-600' }}">
                <span class="font-semibold text-gray-400">You said</span>
                {!! $thumb($voted === 'worth' ? $thumbUpPath : $thumbDownPath, 'h-4 w-4 shrink-0') !!}
                {{ $voted === 'worth' ? 'Worth it' : "I'd skip" }}
            </div>
        @endif
    </div>
</div>
