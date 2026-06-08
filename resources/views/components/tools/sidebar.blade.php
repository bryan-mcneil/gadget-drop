@props(['products' => []])

@if(count($products) > 0)
    <aside class="space-y-4">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-amber-700 uppercase tracking-widest">You might also like</span>
            <span class="flex-1 h-px bg-amber-100"></span>
        </div>
        <div class="space-y-3">
            @foreach($products as $product)
                <a href="{{ route('affiliate.redirect', $product['id']) }}" target="_blank" rel="nofollow sponsored noopener noreferrer"
                    class="group flex gap-3 bg-white border border-gray-200 rounded-xl p-3 hover:border-amber-300 hover:shadow-sm transition-all duration-200">
                    @if(!empty($product['image_url']))
                        <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" class="w-16 h-16 rounded-lg object-cover flex-shrink-0 bg-gray-50" />
                    @else
                        <div class="w-16 h-16 rounded-lg bg-amber-50 flex-shrink-0 flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z" /></svg>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 group-hover:text-amber-700 transition-colors line-clamp-2 leading-snug">{{ $product['name'] }}</p>
                        @if(!empty($product['description']))
                            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2 leading-snug">{{ $product['description'] }}</p>
                        @endif
                        <div class="flex items-center justify-between mt-1.5">
                            @if(!empty($product['price']))
                                <span class="text-sm font-semibold text-gray-900">${{ number_format((float) $product['price'], 2) }}</span>
                            @endif
                            <span class="text-xs text-amber-600 font-medium group-hover:text-amber-800 transition-colors ml-auto">View on Amazon →</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 leading-relaxed">As an Amazon Associate, GadgetDrop earns from qualifying purchases.</p>
    </aside>
@endif
