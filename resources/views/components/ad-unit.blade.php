@props([
    'slot',
    'format' => 'auto',
])

<div {{ $attributes->class(['overflow-hidden']) }}>
    <ins class="adsbygoogle"
        style="display:block"
        data-ad-client="ca-pub-3856395634564582"
        data-ad-slot="{{ $slot }}"
        data-ad-format="{{ $format }}"
        data-full-width-responsive="true"></ins>
</div>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
