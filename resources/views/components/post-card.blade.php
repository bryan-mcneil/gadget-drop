@props(['post'])

@php
    // Type accent system — indigo review / emerald tip / rose news. Every class
    // string is LITERAL (no interpolation) so Tailwind's content scanner keeps
    // all three variants through purge (CLAUDE.md "Build / Tailwind gotchas").
    $type = $post['type'] ?? 'article';
    $accents = [
        'article' => [
            'rail'    => 'bg-indigo-500/20',
            'sweep'   => 'from-transparent via-indigo-500 to-indigo-400',
            'imgBg'   => 'bg-indigo-50',
            'ph'      => 'from-indigo-100 to-purple-100',
            'phText'  => 'text-indigo-300',
            'title'   => 'group-hover:text-indigo-600',
            'more'    => 'text-indigo-600',
            'badge'   => null,
        ],
        'tech_tip' => [
            'rail'    => 'bg-emerald-500/20',
            'sweep'   => 'from-transparent via-emerald-500 to-emerald-400',
            'imgBg'   => 'bg-emerald-50',
            'ph'      => 'from-emerald-100 to-teal-100',
            'phText'  => 'text-emerald-300',
            'title'   => 'group-hover:text-emerald-700',
            'more'    => 'text-emerald-600',
            'badge'   => ['label' => 'Tech Tip', 'classes' => 'bg-emerald-100 text-emerald-700'],
        ],
        'tech_news' => [
            'rail'    => 'bg-rose-500/20',
            'sweep'   => 'from-transparent via-rose-500 to-rose-400',
            'imgBg'   => 'bg-rose-50',
            'ph'      => 'from-rose-100 to-orange-100',
            'phText'  => 'text-rose-300',
            'title'   => 'group-hover:text-rose-700',
            'more'    => 'text-rose-600',
            'badge'   => ['label' => 'News', 'classes' => 'bg-rose-100 text-rose-700'],
        ],
    ];
    $a = $accents[$type] ?? $accents['article'];
@endphp

<a href="{{ route('posts.show', $post['slug']) }}" wire:navigate
    class="group relative bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 flex flex-col">
    {{-- Type accent hairline: a dim static rail that brightens and sweeps in
         from the left on hover. The sweep is the only animation, so it (not the
         hover lift) is what prefers-reduced-motion disables. --}}
    <span aria-hidden="true" class="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden {{ $a['rail'] }}">
        <span class="block h-full w-full -translate-x-full group-hover:translate-x-0 transition-transform duration-500 ease-out motion-reduce:transition-none bg-gradient-to-r {{ $a['sweep'] }}"></span>
    </span>

    <div class="relative overflow-hidden {{ $a['imgBg'] }}">
        @if(!empty($post['featured_image']))
            <x-responsive-image :src="$post['featured_image']" :alt="$post['title']"
                loading="lazy" width="400" height="192" sizes="(min-width: 640px) 400px, 100vw"
                class="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-105"
                style="object-position: {{ $post['featured_image_position'] ?? 'center center' }}" />
        @else
            <div class="w-full h-48 bg-gradient-to-br {{ $a['ph'] }} flex items-center justify-center"><span class="{{ $a['phText'] }} font-black text-5xl select-none">G</span></div>
        @endif
        <div class="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-white to-transparent"></div>
    </div>
    <div class="p-5 flex flex-col flex-1">
        <div class="flex items-center gap-2 mb-2 min-w-0">
            <p class="text-xs text-gray-400 tracking-wide">{{ $post['published_at'] }}</p>
            @if($a['badge'])
                <span class="text-xs {{ $a['badge']['classes'] }} font-bold px-2 py-0.5 rounded-full whitespace-nowrap">{{ $a['badge']['label'] }}</span>
            @endif
        </div>
        <h3 class="font-bold text-gray-900 {{ $a['title'] }} transition-colors leading-snug text-base">{{ $post['title'] }}</h3>
        @if(!empty($post['excerpt']))
            <p class="mt-2 text-sm text-gray-500 line-clamp-2 flex-1">{{ $post['excerpt'] }}</p>
        @endif

        {{-- Price + verdict row: review cards only, and only once PriceIntel's
             honesty gates open (card_verdict !== null). No placeholder data.
             flex-wrap so the sm badge drops to its own line in a narrow column
             instead of overflowing. --}}
        @if(!empty($post['card_verdict']))
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if(($post['card_price'] ?? null) !== null)
                    <span class="text-sm font-extrabold text-gray-900 tabular-nums">${{ number_format((float) $post['card_price'], 2) }}</span>
                @endif
                <x-verdict-badge :verdict="$post['card_verdict']" size="sm" />
            </div>
        @endif

        <div class="mt-4 flex items-center gap-1 text-xs font-semibold {{ $a['more'] }}">
            Read more
            <svg class="w-3.5 h-3.5 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </div>
    </div>
</a>
