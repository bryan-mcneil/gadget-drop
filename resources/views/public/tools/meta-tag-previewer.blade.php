@extends('layouts.public')

@section('content')
    <x-tools.shell title="Meta Tag Previewer" :products="$sidebarProducts">
        <x-slot:description>
            Preview how your page title and description appear in Google search results, Twitter cards, and Facebook shares. Character counters flag when you're in the safe zone.
        </x-slot:description>

        <div x-data="metaTagPreviewer" class="space-y-8">
            {{-- Form --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-5">
                <h2 class="font-bold text-gray-900 text-base">Page meta tags</h2>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-sm font-semibold text-gray-700">Page Title <span class="text-gray-400 font-normal">(&lt;title&gt;)</span></label>
                        <span class="text-xs font-bold tabular-nums" :class="titleColor()" x-text="`${title.length} / 60`"></span>
                    </div>
                    <input type="text" x-model="title" placeholder="Best Wireless Earbuds 2025 | GadgetDrop"
                        class="w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 transition-colors" :class="titleBg()" />
                    <p class="mt-1 text-xs text-gray-400">Keep under 60 characters to avoid truncation in Google.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-sm font-semibold text-gray-700">Meta Description <span class="text-gray-400 font-normal">(&lt;meta name="description"&gt;)</span></label>
                        <span class="text-xs font-bold tabular-nums" :class="descColor()" x-text="`${desc.length} / 160`"></span>
                    </div>
                    <textarea x-model="desc" rows="3" placeholder="Our top picks for wireless earbuds this year, tested for sound quality, battery life, and comfort..."
                        class="w-full px-4 py-2.5 rounded-xl border text-sm resize-none focus:outline-none focus:ring-2 focus:ring-amber-400 transition-colors" :class="descBg()"></textarea>
                    <p class="mt-1 text-xs text-gray-400">Keep under 160 characters. Google may rewrite descriptions that are too long or unhelpful.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Page URL <span class="text-gray-400 font-normal">(for Google breadcrumb display)</span></label>
                    <input type="url" x-model="url" placeholder="https://gadgetdrop.tech/posts/best-wireless-earbuds-2025"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white" />
                </div>

                <div class="pt-4 border-t border-gray-100 space-y-4">
                    <h3 class="font-bold text-gray-800 text-sm">Open Graph / Social <span class="ml-2 text-xs font-normal text-gray-400">Optional, defaults to title &amp; description above</span></h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">OG Title <span class="text-gray-400 font-normal">(og:title)</span></label>
                        <input type="text" x-model="ogTitle" :placeholder="title || 'Same as page title if left empty'" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">OG Description <span class="text-gray-400 font-normal">(og:description)</span></label>
                        <textarea x-model="ogDesc" rows="2" :placeholder="desc || 'Same as meta description if left empty'" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">OG Image URL <span class="text-gray-400 font-normal">(og:image)</span></label>
                        <input type="url" x-model="ogImage" placeholder="https://gadgetdrop.tech/images/og-earbuds.jpg" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white" />
                        <p class="mt-1 text-xs text-gray-400">Recommended: 1200×630 px for Twitter/Facebook. Smaller images may not display.</p>
                    </div>
                </div>
            </div>

            {{-- Preview --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-gray-900 text-base">Live preview</h2>
                    <div class="flex border border-gray-200 rounded-lg overflow-hidden">
                        @foreach(['google' => 'Google', 'twitter' => 'Twitter', 'facebook' => 'Facebook'] as $id => $label)
                            <button @click="preview = '{{ $id }}'" class="px-4 py-1.5 text-xs font-semibold transition-colors" :class="preview === '{{ $id }}' ? 'bg-amber-500 text-white' : 'bg-white text-gray-500 hover:text-gray-700'">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Google --}}
                <div x-show="preview === 'google'" class="bg-white rounded-xl border border-gray-200 p-5 max-w-[600px]">
                    <p class="text-xs text-gray-500 mb-1 truncate" x-text="breadcrumb"></p>
                    <p class="text-[#1a0dab] text-xl font-normal hover:underline cursor-pointer leading-tight mb-1" x-text="trunc(displayTitle, 60)"></p>
                    <p class="text-sm text-gray-600 leading-snug" x-text="trunc(displayDesc, 160)"></p>
                </div>

                {{-- Twitter --}}
                <div x-show="preview === 'twitter'" x-cloak class="max-w-[500px] rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                    <template x-if="ogImage"><img :src="ogImage" alt="OG preview" class="w-full h-52 object-cover" x-on:error="$el.style.display='none'" /></template>
                    <template x-if="!ogImage"><div class="w-full h-52 bg-gray-100 flex items-center justify-center"><span class="text-xs text-gray-400">No og:image set</span></div></template>
                    <div class="p-4 bg-white">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1" x-text="host"></p>
                        <p class="font-bold text-gray-900 text-sm leading-snug" x-text="trunc(socialTitle, 70)"></p>
                        <p class="text-sm text-gray-500 mt-0.5 leading-snug" x-text="trunc(socialDesc, 125)"></p>
                    </div>
                </div>

                {{-- Facebook --}}
                <div x-show="preview === 'facebook'" x-cloak class="max-w-[500px] rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <template x-if="ogImage"><img :src="ogImage" alt="OG preview" class="w-full h-56 object-cover" x-on:error="$el.style.display='none'" /></template>
                    <template x-if="!ogImage"><div class="w-full h-56 bg-gray-100 flex items-center justify-center"><span class="text-xs text-gray-400">No og:image set</span></div></template>
                    <div class="p-3 bg-[#f0f2f5]">
                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-0.5" x-text="host"></p>
                        <p class="font-bold text-gray-900 text-sm leading-snug" x-text="trunc(socialTitle, 88)"></p>
                        <p class="text-sm text-gray-500 leading-snug" x-text="trunc(socialDesc, 110)"></p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-100">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :class="title.length > 0 && title.length <= 60 ? 'bg-green-500' : (title.length > 60 ? 'bg-red-500' : 'bg-gray-300')"></span>
                        <span class="text-xs text-gray-500">Title: <strong class="text-gray-700" x-text="title.length"></strong> chars</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :class="desc.length > 0 && desc.length <= 160 ? 'bg-green-500' : (desc.length > 160 ? 'bg-red-500' : 'bg-gray-300')"></span>
                        <span class="text-xs text-gray-500">Description: <strong class="text-gray-700" x-text="desc.length"></strong> chars</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :class="ogImage ? 'bg-green-500' : 'bg-gray-300'"></span>
                        <span class="text-xs text-gray-500">OG image: <strong class="text-gray-700" x-text="ogImage ? 'set' : 'not set'"></strong></span>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-400">This preview is approximate. Google may rewrite titles and descriptions based on the page content and search query. Social networks cache OG tags. Use the platform's sharing debugger to force a refresh after updating.</p>
        </div>
    </x-tools.shell>
@endsection
