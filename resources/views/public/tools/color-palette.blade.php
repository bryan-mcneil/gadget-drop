@extends('layouts.public')

@section('content')
    <x-tools.shell title="Color Palette Extractor" :products="$sidebarProducts">
        <x-slot:description>
            Extract dominant colors from any image, or build a full tint-and-shade scale from up to three base colors. Click any swatch to copy its HEX code.
        </x-slot:description>

        <div x-data="colorPalette" @file-loaded="handleFile($event.detail)" class="space-y-6">
            {{-- Tabs --}}
            <div class="flex border-b border-gray-200">
                <button @click="tab = 'image'" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px" :class="tab === 'image' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z" /></svg>
                    Extract from Image
                </button>
                <button @click="tab = 'builder'" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px" :class="tab === 'builder' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.375 3.375 0 0 1 3.375 17.625v-2.25A3.375 3.375 0 0 1 6.75 12H21a3.375 3.375 0 0 1 3.375 3.375v2.25A3.375 3.375 0 0 1 21 21H6.75Z" /></svg>
                    Build from Colors
                </button>
            </div>

            {{-- Image tab --}}
            <div x-show="tab === 'image'" class="space-y-6">
                <template x-if="!imageSrc"><div><x-tools.drop-zone /></div></template>
                <template x-if="imageSrc">
                    <div class="relative">
                        <img :src="imageSrc" alt="Uploaded" class="w-full max-h-64 object-contain rounded-2xl bg-gray-100 border border-gray-200" />
                        <button @click="clearImage" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/90 border border-gray-300 hover:bg-white flex items-center justify-center text-gray-600 hover:text-gray-900 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </template>

                <div x-show="imageSrc" class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-gray-700">Colors to extract:</span>
                    <div class="flex gap-1.5">
                        <template x-for="n in counts" :key="n">
                            <button @click="reextract(n)" :disabled="loading" class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-50" :class="count === n ? 'bg-amber-500 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:border-amber-400'" x-text="n"></button>
                        </template>
                    </div>
                </div>

                <div x-show="loading" class="flex items-center gap-3 text-sm text-gray-500">
                    <svg class="w-5 h-5 animate-spin text-amber-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    Extracting colors…
                </div>

                <div x-show="palette.length > 0 && !loading" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-gray-900"><span x-text="palette.length"></span> dominant colors</h2>
                        <button @click="copyAllHex" class="flex items-center gap-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                            Copy all HEX
                        </button>
                    </div>
                    <div class="flex rounded-2xl overflow-hidden h-16 border border-gray-200 shadow-sm">
                        <template x-for="color in palette" :key="color.hex"><div class="flex-1" :style="`background: ${color.hex}`" :title="color.hex"></div></template>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="color in palette" :key="color.hex">
                            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                <div class="h-20" :style="`background: ${color.hex}`"></div>
                                <div class="p-3 space-y-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono font-bold text-sm text-gray-900" x-text="color.hex"></span>
                                        <button @click="copySwatch(color.hex)" class="font-mono text-xs text-gray-600 hover:text-amber-700 transition-colors">copy</button>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-xs text-gray-500" x-text="color.rgb"></span>
                                        <button @click="copySwatch(color.rgb)" class="font-mono text-xs text-gray-600 hover:text-amber-700 transition-colors">copy</button>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-xs text-gray-500" x-text="color.hsl"></span>
                                        <button @click="copySwatch(color.hsl)" class="font-mono text-xs text-gray-600 hover:text-amber-700 transition-colors">copy</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="text-xs text-gray-400">Colors are extracted by sampling pixels and grouping similar shades. The image is processed entirely in your browser. Nothing is uploaded.</p>
            </div>

            {{-- Builder tab --}}
            <div x-show="tab === 'builder'" x-cloak class="space-y-6">
                <p class="text-sm text-gray-500">Pick up to three base colors. Each generates a 10-step tint-and-shade scale. Click any swatch to copy its HEX.</p>

                <div class="flex flex-wrap items-center gap-3">
                    <template x-for="(hex, i) in baseColors" :key="i">
                        <div class="flex items-center gap-2">
                            <label class="relative cursor-pointer group">
                                <span class="block w-10 h-10 rounded-xl border-2 border-white shadow-md ring-1 ring-gray-200 group-hover:ring-amber-400 transition-all" :style="`background: ${hex}`"></span>
                                <input type="color" :value="hex" @input="baseColors[i] = $event.target.value" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                            </label>
                            <span class="font-mono text-sm text-gray-600 w-16" x-text="hex"></span>
                            <button x-show="baseColors.length > 1" @click="removeColor(i)" class="w-6 h-6 rounded-full bg-gray-100 hover:bg-red-100 text-gray-400 hover:text-red-500 flex items-center justify-center transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </template>
                    <button x-show="baseColors.length < 3" @click="addColor" class="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-dashed border-gray-300 text-gray-400 hover:border-amber-400 hover:text-amber-600 text-sm font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Add color
                    </button>
                </div>

                <div class="space-y-3">
                    <div class="flex ml-[72px] gap-0">
                        <template x-for="label in scaleHeaders" :key="label"><div class="flex-1 text-center text-[10px] text-gray-400 font-semibold pb-1" x-text="label"></div></template>
                    </div>
                    <template x-for="(hex, i) in baseColors" :key="i">
                        <div class="flex items-stretch gap-0">
                            <div class="flex-shrink-0 w-[72px] flex flex-col items-center justify-center gap-1.5 pr-2">
                                <div class="w-8 h-8 rounded-lg shadow-sm border border-white ring-1 ring-gray-200" :style="`background: ${hex}`"></div>
                                <button @click="copyRowHex(hex)" class="text-[10px] text-gray-400 hover:text-amber-600 font-medium transition-colors leading-tight text-center">Copy row</button>
                            </div>
                            <div class="flex flex-1 rounded-xl overflow-hidden shadow-sm border border-gray-200" style="height: 72px">
                                <template x-for="sw in scaleFor(hex)" :key="sw.label">
                                    <button @click="copySwatch(sw.hex)" :title="`${sw.label}: ${sw.hex}`"
                                        class="group flex flex-col items-center justify-end pb-2 pt-3 flex-1 min-w-0 transition-all duration-150 hover:scale-y-105 hover:z-10 relative"
                                        :class="sw.label === '500' ? 'ring-2 ring-inset ring-white/40' : ''" :style="`background: ${sw.hex}`">
                                        <span class="text-[10px] font-bold leading-none mb-1" :style="`color: ${sw.textColor}`" x-text="sw.label"></span>
                                        <span class="font-mono text-[9px] leading-none opacity-0 group-hover:opacity-100 transition-opacity" :style="`color: ${sw.textColor}`" x-text="sw.hex"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <p class="text-xs text-gray-400">Scales are generated by mixing the base color with white (tints) and black (shades). Click a swatch to copy its HEX. "Copy row" copies all 10 shades as a newline-separated list.</p>
            </div>
        </div>
    </x-tools.shell>
@endsection
