@props([
    'title',
    'products' => [],
    'workbench' => false,
])

{{-- Page header --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-[100rem] mx-auto px-4 py-10">
        <div class="flex items-center gap-2 mb-2">
            <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 text-xs font-semibold uppercase tracking-widest px-2.5 py-1 rounded-full">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.598-2.167A9.027 9.027 0 0 1 9.496 3.28c-1.586.068-3.07.817-4.188 2.015L4.5 6.122" /></svg>
                Tool
            </span>
        </div>
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">{{ $title }}</h1>
        @isset($description)
            <p class="mt-2 text-gray-500 max-w-2xl">{{ $description }}</p>
        @endisset
    </div>
</div>

{{-- Main content: tool panel + sidebar --}}
<div class="max-w-[100rem] mx-auto px-4 py-8">
    @if($workbench)
        {{-- Workbench layout: the tool owns the full width (canvas + controls rail),
             and product recirculation drops to a full-width row beneath it. --}}
        <div class="space-y-4">
            {{ $slot }}
        </div>
        @if(count($products) > 0)
            <div class="mt-12 pt-8 border-t border-gray-100">
                <x-tools.sidebar :products="$products" layout="row" />
            </div>
        @endif
    @else
        <div class="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">
            <div class="space-y-4">
                {{ $slot }}
            </div>
            <div class="mt-10 lg:mt-0">
                <x-tools.sidebar :products="$products" />
            </div>
        </div>
    @endif
</div>

{{-- Toast container (rendered once per tool page). Driven by Alpine.store('toast'). --}}
<div x-data x-cloak class="fixed bottom-6 inset-x-0 z-[60] flex flex-col items-center gap-2 px-4 pointer-events-none">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div x-transition.opacity.duration.200ms
            class="pointer-events-auto flex items-center gap-2 bg-gray-900 text-white text-sm font-medium px-4 py-2.5 rounded-xl shadow-lg ring-1 ring-white/10">
            <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
            <span x-text="t.message"></span>
        </div>
    </template>
</div>
