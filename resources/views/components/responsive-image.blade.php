@props([
    'src',
    'alt' => '',
    'sizes' => '100vw',
])

@php
    $webpSrcset = \App\Support\ResponsiveImage::webpSrcset($src);
@endphp

{{-- `contents` makes the <picture> wrapper transparent to layout so the inner
     <img> behaves exactly as the original raw <img> did (flow + positioning). --}}
<picture class="contents">
    @if($webpSrcset !== '')
        <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="{{ $sizes }}">
    @endif
    <img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes }}>
</picture>
