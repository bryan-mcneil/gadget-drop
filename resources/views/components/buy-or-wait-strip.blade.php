@props(['data'])

{{-- One-line timing strip under a review's product card. Deliberately
     informational: an INTERNAL link only, no CTA and no price, so the product
     card above keeps its status as the single affiliate call to action.

     Renders nothing unless the product's line resolves to a release cycle
     (ReleaseCycle::forProduct(), which refuses to guess on an ambiguous
     category), and an absent strip is better than a wrong one. --}}

@if(!empty($data))
    <a href="{{ route('buy-or-wait.show', $data['slug']) }}" wire:navigate
        class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-600 hover:border-indigo-200 hover:bg-indigo-50/40 transition-colors">
        <svg class="w-4 h-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
        <span><span class="font-semibold text-gray-900">Buy or wait?</span> On the {{ $data['name'] }} line, {{ $data['short'] }}.</span>
        <span class="font-semibold text-indigo-600 hover:text-indigo-700">See the timing &rarr;</span>
    </a>
@endif
