@props(['slug', 'name', 'description', 'icon' => 'wrench'])

<a href="{{ route('tools.' . $slug) }}" class="group flex flex-col bg-white border border-gray-200 rounded-2xl p-6 hover:border-amber-300 hover:shadow-md transition-all duration-200">
    <div class="w-12 h-12 rounded-xl bg-amber-50 group-hover:bg-amber-100 flex items-center justify-center mb-4 transition-colors text-amber-600">
        <x-tool-icon :icon="$icon" class="w-6 h-6" />
    </div>
    <h2 class="text-base font-semibold text-gray-900 group-hover:text-amber-700 transition-colors mb-1">{{ $name }}</h2>
    <p class="text-sm text-gray-500 leading-relaxed flex-1">{{ $description }}</p>
    <span class="mt-4 text-xs font-medium text-amber-600 group-hover:text-amber-800 transition-colors flex items-center gap-1">
        Use tool
        <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
    </span>
</a>
