@props([
    'title',
    'posts' => [],
    'emptyLabel' => null,
    'color' => 'indigo',
])

@php
    // Colored accent bar + title carry the type identity; the section divider
    // itself is the neutral border-gray-200 the card system uses, so the whole
    // page reads on one surface scale (10.3).
    $palette = [
        'indigo'  => ['accent' => 'bg-indigo-400',  'title' => 'text-indigo-700',  'hover' => 'group-hover:text-indigo-600',  'placeholder' => 'bg-indigo-50',  'icon' => 'text-indigo-300'],
        'emerald' => ['accent' => 'bg-emerald-400', 'title' => 'text-emerald-700', 'hover' => 'group-hover:text-emerald-600', 'placeholder' => 'bg-emerald-50', 'icon' => 'text-emerald-300'],
        'rose'    => ['accent' => 'bg-rose-400',    'title' => 'text-rose-700',    'hover' => 'group-hover:text-rose-600',    'placeholder' => 'bg-rose-50',    'icon' => 'text-rose-300'],
    ];
    $c = $palette[$color] ?? $palette['indigo'];
    $count = is_countable($posts) ? count($posts) : 0;
@endphp

@if($count > 0 || $emptyLabel)
    <div class="border-b border-gray-200 pb-8">
        <div class="flex items-center gap-2 mb-4">
            <span class="block w-1 h-4 rounded-full {{ $c['accent'] }}"></span>
            <h3 class="text-xs font-bold uppercase tracking-widest {{ $c['title'] }}">{{ $title }}</h3>
        </div>
        @if($count === 0)
            <p class="text-sm text-gray-400">{{ $emptyLabel }}</p>
        @else
            <ul class="space-y-4">
                @foreach($posts as $p)
                    <li class="group">
                        <a href="{{ route('posts.show', $p['slug']) }}" wire:navigate class="flex gap-3 items-start">
                            @if(!empty($p['featured_image']))
                                <x-responsive-image :src="$p['featured_image']" :alt="$p['title']" sizes="56px" loading="lazy" width="56" height="56"
                                    class="w-14 h-14 rounded-lg object-cover flex-shrink-0 bg-gray-100" />
                            @else
                                <div class="w-14 h-14 rounded-lg {{ $c['placeholder'] }} flex-shrink-0 flex items-center justify-center">
                                    <span class="{{ $c['icon'] }} text-xl font-bold">G</span>
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800 {{ $c['hover'] }} leading-snug transition-colors">{{ $p['title'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $p['published_at'] }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
