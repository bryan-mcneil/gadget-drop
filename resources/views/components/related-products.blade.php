@props([
    'products' => [],
    'postId',
    'color' => 'indigo',
])

@php
    $palette = [
        'indigo'  => ['bar' => 'bg-indigo-400',  'title' => 'text-indigo-700',  'border' => 'border-indigo-200'],
        'emerald' => ['bar' => 'bg-emerald-400', 'title' => 'text-emerald-700', 'border' => 'border-emerald-200'],
        'rose'    => ['bar' => 'bg-rose-400',    'title' => 'text-rose-700',    'border' => 'border-rose-200'],
    ];
    $c = $palette[$color] ?? $palette['indigo'];
@endphp

<div class="border-b {{ $c['border'] }} pb-8">
    <div class="flex items-center gap-2 mb-4">
        <span class="block w-1 h-4 rounded-full {{ $c['bar'] }}"></span>
        <h3 class="text-xs font-bold uppercase tracking-widest {{ $c['title'] }}">Shop Related</h3>
    </div>
    <ul class="space-y-3">
        @foreach($products as $p)
            <li>
                <a href="{{ route('affiliate.redirect', ['product' => $p['id'], 'post' => $postId]) }}"
                    target="_blank" rel="nofollow sponsored" class="flex items-center gap-3 group">
                    @if(!empty($p['image_url']))
                        <img src="{{ $p['image_url'] }}" alt="{{ $p['name'] }}" loading="lazy" width="48" height="48"
                            class="w-12 h-12 rounded-lg object-contain bg-gray-50 flex-shrink-0" />
                    @else
                        <div class="w-12 h-12 rounded-lg bg-orange-50 flex-shrink-0 flex items-center justify-center">
                            <span class="text-orange-300 text-lg font-bold">A</span>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-800 group-hover:text-orange-600 leading-snug transition-colors line-clamp-2">{{ $p['name'] }}</p>
                        @if(!empty($p['price']))
                            <p class="text-xs text-gray-500 mt-0.5">${{ $p['price'] }}</p>
                        @endif
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
</div>
