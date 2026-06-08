@props([
    'title',
    'products' => [],
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
    <div class="lg:grid lg:grid-cols-[1fr_288px] lg:gap-8">
        <div class="space-y-4">
            {{ $slot }}
        </div>
        <div class="mt-10 lg:mt-0">
            <x-tools.sidebar :products="$products" />
        </div>
    </div>
</div>
