@props(['verdict' => null, 'dropPct' => null, 'size' => 'md'])

@php
    // The verdict language for the whole site (tracked-price widget, product
    // card, /deals) lives here, once. PriceIntel decides the verdict tier
    // ('lowest'|'good'|'typical'|'elevated'); this component only renders it.
    //
    // Labels + full colour class strings are kept LITERAL (no interpolation)
    // so Tailwind's content scanner never purges them — see CLAUDE.md
    // "Build / Tailwind gotchas".
    $verdictBadge = [
        'lowest'   => ['label' => 'Lowest tracked price', 'classes' => 'bg-green-100 text-green-800 ring-green-200'],
        'good'     => ['label' => 'Below typical price',  'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'typical'  => ['label' => 'Typical price',        'classes' => 'bg-gray-100 text-gray-600 ring-gray-200'],
        'elevated' => ['label' => 'Higher than usual',    'classes' => 'bg-amber-50 text-amber-700 ring-amber-200'],
    ];

    // Size → full literal utility string. 'md' reproduces the tracked-price
    // widget's badge scale exactly; 'sm' is the tighter chip used on cards.
    $sizeClasses = [
        'sm' => 'text-[10px] px-2 py-0.5',
        'md' => 'text-[11px] px-2.5 py-1',
    ];

    $meta = $verdictBadge[$verdict] ?? null;
    $sizing = $sizeClasses[$size] ?? $sizeClasses['md'];

    // Append the magnitude only for the deal tiers, and only once it clears 1%.
    // dropPct is pre-rounded to one decimal by PriceIntel, so echo it verbatim.
    $label = $meta['label'] ?? '';
    if ($meta !== null && $dropPct !== null && $dropPct >= 1 && in_array($verdict, ['lowest', 'good'], true)) {
        $label .= ' · '.$dropPct.'% below typical';
    }
@endphp

@if($meta !== null)
    <span class="inline-block font-bold rounded-full ring-1 ring-inset whitespace-nowrap {{ $sizing }} {{ $meta['classes'] }}">{{ $label }}</span>
@endif
