@props([
    'post',
    'emerald' => false,
])

@php
    $fmtNum = function ($n) {
        return $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k' : (string) $n;
    };
@endphp

<a href="{{ route('posts.show', $post['slug']) }}" wire:navigate
    class="group flex flex-col bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-md hover:-translate-y-0.5 motion-reduce:hover:translate-y-0 transition-all duration-200">
    @if(!empty($post['featured_image']))
        <x-responsive-image :src="$post['featured_image']" :alt="$post['title']" loading="lazy" width="400" height="176" sizes="(min-width: 768px) 33vw, 100vw" class="w-full h-44 object-cover" />
    @else
        <div class="w-full h-44 flex items-center justify-center {{ $emerald ? 'bg-emerald-50' : 'bg-indigo-50' }}">
            <span class="font-extrabold text-6xl select-none {{ $emerald ? 'text-emerald-200' : 'text-indigo-200' }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($post['title'], 0, 1)) }}</span>
        </div>
    @endif

    <div class="flex flex-col flex-1 p-5">
        @if($emerald)
            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 mb-2">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                Tech Tip
            </span>
        @endif
        <h3 class="font-semibold text-gray-900 leading-snug transition-colors {{ $emerald ? 'group-hover:text-emerald-700' : 'group-hover:text-indigo-600' }}">{{ $post['title'] }}</h3>
        @if(!empty($post['excerpt']))
            <p class="text-sm text-gray-500 mt-2 line-clamp-2 flex-1">{{ $post['excerpt'] }}</p>
        @endif
        <div class="flex items-center justify-between mt-4">
            <span class="text-xs text-gray-400">{{ $post['published_at'] }}</span>
            @if(($post['view_count'] ?? 0) > 0)
                <span class="text-xs text-gray-400">{{ $fmtNum($post['view_count']) }} views</span>
            @endif
        </div>
    </div>
</a>
