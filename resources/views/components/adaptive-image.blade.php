@props([
    'src',
    'alt' => '',
    'fit' => 'cover',
    'position' => 'center center',
    'wrapperClass' => '',
    'loading' => 'lazy',
    'fetchpriority' => null,
    'width' => null,
    'height' => null,
])

@php
    $fitClass = $fit === 'contain' ? 'object-contain bg-gray-50' : 'object-cover';
@endphp

<div class="{{ $wrapperClass }}">
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        loading="{{ $loading }}"
        @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
        @if($width) width="{{ $width }}" @endif
        @if($height) height="{{ $height }}" @endif
        {{ $attributes->class([$fitClass]) }}
        @if($fit !== 'contain') style="object-position: {{ $position }};" @endif
    />
</div>
