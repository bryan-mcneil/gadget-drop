@props(['verdict', 'confidence' => null, 'size' => 'md'])

{{-- Shared verdict chip for the /buy-or-wait index, the cycle hero and the
     review-page strip, so all three can never drift apart.

     Class strings are written out in full on purpose: Tailwind purges on a
     literal content scan, so an interpolated "bg-{$tone}-100" would vanish from
     the built CSS. BuyOrWait::tone() stays the semantic source; this is its
     rendering. --}}

@php
    $palette = match ($verdict) {
        \App\Support\BuyOrWait::BUY => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        \App\Support\BuyOrWait::WAIT_FOR_REFRESH => 'bg-amber-100 text-amber-800 ring-amber-200',
        \App\Support\BuyOrWait::WAIT_FOR_PRICE => 'bg-sky-100 text-sky-800 ring-sky-200',
        default => 'bg-gray-100 text-gray-700 ring-gray-200',
    };

    $sizing = $size === 'sm'
        ? 'text-[10px] px-2 py-0.5 gap-1'
        : 'text-xs px-2.5 py-1 gap-1.5';
@endphp

<span class="inline-flex w-fit items-center rounded-full font-bold ring-1 {{ $palette }} {{ $sizing }}">
    {{ \App\Support\BuyOrWait::label($verdict) }}
    @if($confidence)
        {{-- Confidence is part of the claim, not a footnote: a verdict built on
             thin data has to say so where the verdict is read. --}}
        <span class="font-medium opacity-70">· {{ $confidence }} confidence</span>
    @endif
</span>
