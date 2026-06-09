@props(['post'])

<a href="{{ route('posts.show', $post['slug']) }}"
    class="group bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow duration-300 flex flex-col">
    <div class="relative overflow-hidden bg-indigo-50">
        @if(!empty($post['featured_image']))
            <x-responsive-image :src="$post['featured_image']" :alt="$post['title']"
                loading="lazy" width="400" height="192" sizes="(min-width: 640px) 400px, 100vw"
                class="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-105"
                style="object-position: {{ $post['featured_image_position'] ?? 'center center' }}" />
        @else
            <div class="w-full h-48 bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center"><span class="text-indigo-300 font-black text-5xl select-none">G</span></div>
        @endif
        <div class="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-white to-transparent"></div>
    </div>
    <div class="p-5 flex flex-col flex-1">
        <div class="flex items-center gap-2 mb-2">
            <p class="text-xs text-gray-400 tracking-wide">{{ $post['published_at'] }}</p>
            @if(($post['type'] ?? null) === 'tech_tip')
                <span class="text-xs bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full">Tech Tip</span>
            @endif
        </div>
        <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition-colors leading-snug text-base">{{ $post['title'] }}</h3>
        @if(!empty($post['excerpt']))
            <p class="mt-2 text-sm text-gray-500 line-clamp-2 flex-1">{{ $post['excerpt'] }}</p>
        @endif
        <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
            Read more
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </div>
    </div>
</a>
