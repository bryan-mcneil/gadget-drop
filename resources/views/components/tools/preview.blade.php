@props(['slug'])

@php
    $dot = '<div class="flex items-center gap-1.5 mb-3"><span class="w-2.5 h-2.5 rounded-full bg-red-500/60"></span><span class="w-2.5 h-2.5 rounded-full bg-yellow-500/60"></span><span class="w-2.5 h-2.5 rounded-full bg-green-500/60"></span>';
@endphp

@switch($slug)
    @case('json-validator')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 font-mono text-xs leading-relaxed overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">json-validator</span></div>
            <div class="space-y-0.5">
                <p><span class="text-gray-500">{</span></p>
                <p class="pl-4"><span class="text-amber-400">"name"</span><span class="text-gray-400">: </span><span class="text-green-400">"GadgetDrop"</span><span class="text-gray-500">,</span></p>
                <p class="pl-4"><span class="text-amber-400">"version"</span><span class="text-gray-400">: </span><span class="text-blue-400">2</span><span class="text-gray-500">,</span></p>
                <p class="pl-4"><span class="text-amber-400">"tools"</span><span class="text-gray-400">: </span><span class="text-gray-500">[</span></p>
                <p class="pl-8"><span class="text-green-400">"json-validator"</span><span class="text-gray-500">,</span></p>
                <p class="pl-8"><span class="text-green-400">"js-css-minifier"</span></p>
                <p class="pl-4"><span class="text-gray-500">]</span></p>
                <p><span class="text-gray-500">}</span></p>
            </div>
            <div class="mt-3 flex items-center gap-2 text-green-400">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <span>Valid JSON, formatted successfully.</span>
            </div>
        </div>
        @break

    @case('js-css-minifier')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 font-mono text-xs leading-relaxed overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">js-minifier</span></div>
            <div class="space-y-1">
                <p class="text-gray-500 text-xs uppercase tracking-widest mb-2">Before</p>
                <p class="text-gray-400 line-clamp-2">function greet(name) {</p>
                <p class="text-gray-400 pl-4">  return "Hello, " + name + "!";</p>
                <p class="text-gray-400">}</p>
            </div>
            <div class="my-3 flex items-center gap-2"><span class="flex-1 h-px bg-gray-800"></span><span class="text-xs text-gray-600">minified</span><span class="flex-1 h-px bg-gray-800"></span></div>
            <p class="text-green-400">function greet(n){return"Hello, "+n+"!"}</p>
            <div class="mt-3 flex items-center gap-2">
                <span class="text-xs font-semibold bg-green-900/50 text-green-400 px-2 py-0.5 rounded-full border border-green-800/50">Saved 28%</span>
                <span class="text-gray-600 text-xs">68 B → 49 B</span>
            </div>
        </div>
        @break

    @case('image-converter')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">image-converter</span></div>
            <div class="flex gap-3 items-center mb-3">
                <div class="w-20 h-16 rounded-lg bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-600">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z" /></svg>
                </div>
                <div class="flex-1 space-y-2">
                    <div class="flex gap-1.5">
                        <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-amber-500 text-white">WebP</span>
                        <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-gray-800 text-gray-500">JPG</span>
                        <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-gray-800 text-gray-500">PNG</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">Quality</span>
                        <div class="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden"><div class="h-full w-[90%] bg-amber-500 rounded-full"></div></div>
                        <span class="text-xs text-amber-400 font-bold">90%</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-gray-500">photo.jpg 2.4 MB</span>
                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                <span class="text-green-400 font-semibold">photo.webp 0.8 MB</span>
                <span class="ml-auto text-xs font-bold bg-green-900/50 text-green-400 px-2 py-0.5 rounded-full border border-green-800/50">-67%</span>
            </div>
        </div>
        @break

    @case('password-generator')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 font-mono text-xs overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">password-generator</span></div>
            <div class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 mb-3 flex items-center justify-between">
                <span class="text-green-400 tracking-widest">K9#mPx@2qL!nR5vT</span>
                <span class="text-amber-400 text-xs font-semibold ml-2">Copy</span>
            </div>
            <div class="flex gap-1.5 mb-2">
                <span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 font-bold">A-Z</span>
                <span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 font-bold">a-z</span>
                <span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 font-bold">0-9</span>
                <span class="text-xs px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/30 font-bold">!@#</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-gray-500 text-xs">Length</span>
                <div class="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden"><div class="h-full w-[50%] bg-amber-500 rounded-full"></div></div>
                <span class="text-amber-400 font-bold text-xs">16</span>
                <span class="ml-2 text-xs font-bold bg-green-900/50 text-green-400 px-2 py-0.5 rounded-full border border-green-800/50">Strong</span>
            </div>
        </div>
        @break

    @case('base64-encoder')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 font-mono text-xs overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">base64-encoder</span></div>
            <div class="space-y-2">
                <div><p class="text-gray-500 text-xs mb-1">Input</p><div class="bg-gray-900 border border-gray-700 rounded px-2 py-1.5"><span class="text-gray-300">Hello, GadgetDrop!</span></div></div>
                <div class="flex items-center gap-2"><span class="flex-1 h-px bg-gray-800"></span><span class="text-xs bg-amber-500 text-white px-2 py-0.5 rounded font-bold">Encode</span><span class="flex-1 h-px bg-gray-800"></span></div>
                <div><p class="text-gray-500 text-xs mb-1">Output</p><div class="bg-gray-900 border border-gray-700 rounded px-2 py-1.5"><span class="text-amber-400 break-all">SGVsbG8sIEdhZGdldERyb3Ah</span></div></div>
            </div>
        </div>
        @break

    @case('color-palette')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">color-palette</span></div>
            <div class="flex gap-2 mb-3">
                @foreach(['#1a237e','#0d47a1','#1565c0','#42a5f5','#90caf9'] as $color)
                    <div class="flex-1 flex flex-col items-center gap-1.5"><div class="w-full h-10 rounded-lg border border-white/10" style="background: {{ $color }}"></div><span class="text-gray-500 font-mono" style="font-size: 9px">{{ $color }}</span></div>
                @endforeach
            </div>
            <div class="flex items-center justify-between text-xs"><span class="text-gray-500">5 colors extracted</span><span class="text-amber-400 font-semibold">Copy all</span></div>
        </div>
        @break

    @case('meta-tag-previewer')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 text-xs overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">meta-tag-previewer</span></div>
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <p class="text-gray-400 text-xs mb-1" style="font-size: 10px">gadgetdrop.tech</p>
                <p class="text-blue-700 font-medium leading-tight mb-1" style="font-size: 11px">Best Wireless Earbuds 2025 | GadgetDrop</p>
                <p class="text-gray-600 leading-snug" style="font-size: 10px">Our top picks for wireless earbuds this year. Tested and reviewed so you don't have to...</p>
            </div>
            <div class="mt-2 flex items-center gap-1.5">
                <span class="text-xs font-bold text-green-400">✓</span><span class="text-xs text-gray-500">Title 42 chars</span>
                <span class="mx-1 text-gray-700">·</span>
                <span class="text-xs font-bold text-green-400">✓</span><span class="text-xs text-gray-500">Desc 98 chars</span>
            </div>
        </div>
        @break

    @case('image-cropper')
        <div class="rounded-xl bg-gray-950 border border-gray-800 p-4 overflow-hidden select-none">
            {!! $dot !!}<span class="ml-2 text-gray-600 text-xs">image-cropper</span></div>
            <div class="flex gap-1.5 mb-3">
                <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-gray-800 text-gray-500">Free</span>
                <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-gray-800 text-gray-500">1:1</span>
                <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-amber-500 text-white">16:9</span>
                <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-gray-800 text-gray-500">4:3</span>
                <span class="text-xs px-2 py-1 rounded-lg font-semibold bg-gray-800 text-gray-500">Circle</span>
            </div>
            <div class="relative bg-gray-900 rounded-lg overflow-hidden h-20 flex items-center justify-center border border-gray-700">
                <div class="absolute inset-0 opacity-30" style="background-image: linear-gradient(45deg, #374151 25%, transparent 25%), linear-gradient(-45deg, #374151 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #374151 75%), linear-gradient(-45deg, transparent 75%, #374151 75%); background-size: 8px 8px; background-position: 0 0, 0 4px, 4px -4px, -4px 0px;"></div>
                <div class="relative border-2 border-amber-400 w-28 h-16 rounded-sm">
                    <div class="absolute -top-1 -left-1 w-3 h-3 border-t-2 border-l-2 border-amber-400"></div>
                    <div class="absolute -top-1 -right-1 w-3 h-3 border-t-2 border-r-2 border-amber-400"></div>
                    <div class="absolute -bottom-1 -left-1 w-3 h-3 border-b-2 border-l-2 border-amber-400"></div>
                    <div class="absolute -bottom-1 -right-1 w-3 h-3 border-b-2 border-r-2 border-amber-400"></div>
                </div>
            </div>
        </div>
        @break
@endswitch
