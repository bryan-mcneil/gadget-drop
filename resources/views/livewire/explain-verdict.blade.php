<div class="mt-4">
    @if($phase !== 'gated' && $phase !== 'explained')
        <button type="button" wire:click="explain" wire:loading.attr="disabled" wire:target="explain"
            class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-60">
            <svg wire:loading wire:target="explain" class="h-4 w-4 shrink-0 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
            </svg>
            <svg wire:loading.remove wire:target="explain" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
            </svg>
            <span wire:loading.remove wire:target="explain">Explain this verdict</span>
            <span wire:loading wire:target="explain">Asking Claude&hellip;</span>
        </button>

        @if($phase === 'failed')
            <p class="mt-2 text-xs text-gray-500">Couldn't generate an explanation right now. Try again in a moment.</p>
        @endif
    @endif

    @if($phase === 'gated')
        {{-- No Claude call is made for this case: there is nothing but the
             absence of data to explain, so a static, honest line stands in
             for it instead of sending an incomplete payload and risking an
             invented rationale. Deliberately NOT labeled "Explained by
             Claude" below, since Claude didn't write it. --}}
        <p class="text-sm text-gray-600 leading-relaxed">
            Not enough tracked price history yet to weigh into this verdict
            ({{ \App\Support\PriceIntel::MIN_POINTS }}+ snapshots spanning {{ \App\Support\PriceIntel::MIN_SPAN_DAYS }}+ days is the minimum).
        </p>
    @endif

    {{-- Always rendered, never wrapped in a phase-conditional: Livewire's
         stream() call in ExplainVerdict::explain() needs this exact element
         present in the DOM at click-time to find it (wire:stream must match
         a target that already exists before the streamed response starts
         arriving). It's visually empty until content streams in. --}}
    <div class="{{ $phase === 'explained' ? 'mt-3' : '' }}">
        @if($phase === 'explained')
            <p class="mb-1.5 text-[11px] font-bold uppercase tracking-widest text-indigo-500">Explained by Claude</p>
        @endif
        <p wire:stream="explanation" class="text-sm text-gray-700 leading-relaxed"></p>
    </div>
</div>
