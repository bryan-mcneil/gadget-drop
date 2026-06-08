@props([
    'product',   // array: id, name, description, price, image_url
    'postId',
])

<div class="flex gap-4 bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
    @if(!empty($product['image_url']))
        <img src="{{ $product['image_url'] }}" alt="Product photo: {{ $product['name'] }}" loading="lazy"
            class="w-32 h-32 object-contain rounded-lg flex-shrink-0" />
    @endif
    <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-gray-900 leading-snug">{{ $product['name'] }}</h3>
        @if(!empty($product['description']))
            <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $product['description'] }}</p>
        @endif
        <div class="mt-4">
            @if(!empty($product['price']))
                <p class="text-2xl font-bold text-gray-900 mb-2">${{ $product['price'] }}</p>
            @endif
            <a href="{{ route('affiliate.redirect', ['product' => $product['id'], 'post' => $postId]) }}"
                target="_blank" rel="nofollow sponsored"
                class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-3 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2">
                View on Amazon →
            </a>
        </div>
    </div>
</div>
