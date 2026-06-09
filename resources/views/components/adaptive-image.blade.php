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
    'sizes' => '(min-width: 1024px) 768px, 100vw',
])

@php
    $fitClass = $fit === 'contain' ? 'object-contain bg-gray-50' : 'object-cover';
@endphp

<div class="{{ $wrapperClass }}">
    {{-- NB: never put @if/@endif directives inside a <x-component> tag — Blade's
         component compiler parses the tag before directives, so they break it and
         the attributes leak as raw text. Use bound (:) attributes instead; the
         attribute bag drops null/false values. --}}
    <x-responsive-image
        :src="$src"
        :alt="$alt"
        :sizes="$sizes"
        :loading="$loading"
        :fetchpriority="$fetchpriority"
        :width="$width"
        :height="$height"
        :style="$fit !== 'contain' ? 'object-position: ' . $position . ';' : null"
        {{ $attributes->class([$fitClass]) }}
    />
</div>
