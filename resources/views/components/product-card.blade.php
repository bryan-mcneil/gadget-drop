@props([
    'product',   // array: id, name, description, price, image_url
    'postId',
])

<div class="flex gap-4 bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
    @if(!empty($product['image_url']))
        <img src="{{ $product['image_url'] }}" alt="Product photo: {{ $product['name'] }}" loading="lazy" width="128" height="128"
            class="w-32 h-32 object-contain rounded-lg flex-shrink-0" />
    @endif
    <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-gray-900 leading-snug">{{ $product['name'] }}</h3>
        @if(!empty($product['description']))
            <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $product['description'] }}</p>
        @endif
        <div class="mt-4">
            @php
                // Verdict rides in on $product['price_intel'] (PublicController builds
                // it once per page; null on tip/news posts) — never call
                // PriceIntel::stats() from here.
                $verdict = $product['price_intel']['verdict'] ?? null;
            @endphp
            @if(!empty($product['price']) || $verdict !== null)
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 mb-2">
                    @if(!empty($product['price']))
                        <p class="text-2xl font-bold text-gray-900">${{ $product['price'] }}</p>
                    @endif
                    {{-- No :drop-pct here on purpose: the full "· N% below typical"
                         suffix overflows the ~167px column beside the image at 375px
                         (plan 01 Risks). The widget below carries the magnitude. --}}
                    <x-verdict-badge :verdict="$verdict" size="sm" />
                </div>
            @endif
            @if($verdict !== null)
                {{-- Internal informational link, not a CTA — the affiliate button below
                     stays the card's single CTA. Plain link (no wire:navigate): it
                     targets a #fragment, and the browser's native navigation is what
                     reliably scrolls to it. --}}
                <a href="{{ route('how-we-review') }}#deal-verdicts"
                    class="block w-fit text-xs text-gray-500 hover:text-gray-700 underline decoration-gray-300 underline-offset-2 mb-2">
                    How we call deals
                </a>
            @endif
            <a href="{{ route('affiliate.redirect', ['product' => $product['id'], 'post' => $postId]) }}"
                target="_blank" rel="nofollow sponsored"
                class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-3 rounded-lg transition focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2">
                View on Amazon →
            </a>
        </div>
    </div>
</div>
