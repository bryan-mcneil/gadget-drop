@props([
    'slot',
    'format' => 'auto',
])

{{-- Renders nothing while AdSense is disabled (services.adsense.enabled) so
     callers can keep their <x-ad-unit> slots in place for later re-enabling. --}}
@if(config('services.adsense.enabled'))
<div {{ $attributes->class(['overflow-hidden']) }}>
    <ins class="adsbygoogle"
        style="display:block"
        data-ad-client="{{ config('services.adsense.client') }}"
        data-ad-slot="{{ $slot }}"
        data-ad-format="{{ $format }}"
        data-full-width-responsive="true"></ins>
</div>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
@endif