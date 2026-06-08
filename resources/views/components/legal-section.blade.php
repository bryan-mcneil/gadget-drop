@props(['title'])

<section class="space-y-3">
    <h2 class="text-lg font-bold text-gray-900">{{ $title }}</h2>
    <div class="text-gray-600 leading-relaxed space-y-3 text-sm">{{ $slot }}</div>
</section>
