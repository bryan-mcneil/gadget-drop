@php
    // Static, developer-controlled icon markup — defined once, echoed with {!! !!}
    // in both the ballot and result states so the long SVG paths aren't repeated.
    $thumbUp = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904M14 9h2.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" /></svg>';
    $thumbDown = '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.75h2.25m8.024-9.75c.011.05.028.1.052.148.591 1.2.924 2.55.924 3.977a8.96 8.96 0 0 1-.999 4.125m.023-8.25c-.076-.365.183-.75.575-.75h.908c.889 0 1.713.518 1.972 1.368.339 1.11.521 2.287.521 3.507 0 1.553-.295 3.036-.831 4.398C20.613 14.297 19.833 14.75 19 14.75h-1.053c-.472 0-.745-.556-.5-.96a8.95 8.95 0 0 0 .303-.54m.023-8.25H16.48a4.5 4.5 0 0 1-1.423-.23l-3.114-1.04a4.5 4.5 0 0 0-1.423-.23H6.504c-.618 0-1.217.247-1.605.729A11.95 11.95 0 0 0 2.25 11.75c0 .434.023.863.068 1.285.114 1.022 1.032 1.715 2.058 1.715h3.126c.618 0 .991.724.725 1.282A7.471 7.471 0 0 0 7.5 19.25a2.25 2.25 0 0 0 2.25 2.25.75.75 0 0 0 .75-.75v-.633c0-.573.11-1.14.322-1.672.304-.76.93-1.33 1.653-1.715a9.04 9.04 0 0 0 2.86-2.4c.498-.634 1.226-1.08 2.032-1.08h.384" /></svg>';
@endphp

<div class="my-10">
    <div class="rounded-2xl border border-gray-200 bg-white px-6 py-7 text-center shadow-sm">
        @if ($voted === null)
            {{-- Ballot --}}
            <p class="text-base font-bold text-gray-900">Was this worth it?</p>
            <p class="mt-1 mb-5 text-xs text-gray-500">One tap, no account — your vote is anonymous.</p>

            <div class="flex items-center justify-center gap-3">
                <button type="button" wire:click="vote('worth')" wire:loading.attr="disabled" wire:target="vote"
                    aria-pressed="false" aria-label="Vote: worth it"
                    class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-2.5 text-sm font-bold text-emerald-700 transition-colors hover:border-emerald-300 hover:bg-emerald-100 disabled:opacity-60">
                    {!! $thumbUp !!}
                    Worth it
                </button>
                <button type="button" wire:click="vote('skip')" wire:loading.attr="disabled" wire:target="vote"
                    aria-pressed="false" aria-label="Vote: I would skip it"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-5 py-2.5 text-sm font-bold text-gray-600 transition-colors hover:border-gray-300 hover:bg-gray-100 disabled:opacity-60">
                    {!! $thumbDown !!}
                    I'd skip
                </button>
            </div>
        @else
            {{-- Result --}}
            <p class="text-base font-bold text-gray-900">Thanks for voting!</p>

            @if ($summary['pct'] !== null)
                <p class="mt-1 text-sm text-gray-600">
                    <span class="font-extrabold text-emerald-600">{{ $summary['pct'] }}%</span>
                    of {{ $summary['total'] }} readers say it's worth it
                </p>

                <div class="mx-auto mt-4 max-w-xs">
                    <div class="flex h-2.5 overflow-hidden rounded-full bg-gray-100" role="img"
                        aria-label="{{ $summary['pct'] }}% of {{ $summary['total'] }} readers say worth it">
                        <div class="h-full bg-emerald-500" style="width: {{ $summary['pct'] }}%"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-semibold text-gray-400">
                        <span>{{ $summary['worth'] }} worth it</span>
                        <span>{{ $summary['skip'] }} would skip</span>
                    </div>
                </div>
            @else
                <p class="mt-1 text-sm text-gray-500">
                    Early votes — you're one of the first {{ $summary['total'] }}. Check back as more readers weigh in.
                </p>
            @endif

            {{-- The visitor's own choice stays visible as a disabled toggle (context + a11y). --}}
            <div class="mt-5 flex items-center justify-center gap-3">
                <span aria-pressed="{{ $voted === 'worth' ? 'true' : 'false' }}" role="button" aria-disabled="true"
                    class="inline-flex items-center gap-2 rounded-xl border px-5 py-2.5 text-sm font-bold {{ $voted === 'worth' ? 'border-emerald-300 bg-emerald-100 text-emerald-800' : 'border-gray-200 bg-white text-gray-300' }}">
                    {!! $thumbUp !!}
                    Worth it
                </span>
                <span aria-pressed="{{ $voted === 'skip' ? 'true' : 'false' }}" role="button" aria-disabled="true"
                    class="inline-flex items-center gap-2 rounded-xl border px-5 py-2.5 text-sm font-bold {{ $voted === 'skip' ? 'border-gray-300 bg-gray-200 text-gray-800' : 'border-gray-200 bg-white text-gray-300' }}">
                    {!! $thumbDown !!}
                    I'd skip
                </span>
            </div>
        @endif
    </div>
</div>
