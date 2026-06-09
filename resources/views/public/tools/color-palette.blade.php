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
                <button @click="tab = 'harmony'" class="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px" :class="tab === 'harmony' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                    Color Harmony
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
                        <div class="relative" @click.outside="exportMenuOpen = false">
                            <button @click="exportMenuOpen = !exportMenuOpen"
                                class="flex items-center gap-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Export
                                <svg class="w-3 h-3 transition-transform" :class="exportMenuOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <div x-show="exportMenuOpen" x-cloak
                                class="absolute right-0 top-full mt-1 z-20 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-1 overflow-hidden">
                                <button @click="copyPaletteAs('hex')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">#HEX</span> HEX list
                                </button>
                                <button @click="copyPaletteAs('css')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">CSS</span> CSS variables
                                </button>
                                <button @click="copyPaletteAs('tailwind')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">TW</span> Tailwind config
                                </button>
                                <button @click="copyPaletteAs('json')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">{}</span> JSON
                                </button>
                            </div>
                        </div>
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
                            <div class="relative flex-shrink-0 w-[72px] flex flex-col items-center justify-center gap-1.5 pr-2"
                                @click.outside="if (rowMenuOpen === i) rowMenuOpen = -1">
                                <div class="w-8 h-8 rounded-lg shadow-sm border border-white ring-1 ring-gray-200" :style="`background: ${hex}`"></div>
                                <button @click="rowMenuOpen = (rowMenuOpen === i ? -1 : i)"
                                    class="flex items-center gap-0.5 text-[10px] text-gray-400 hover:text-amber-600 font-medium transition-colors leading-tight">
                                    Export
                                    <svg class="w-2 h-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                                <div x-show="rowMenuOpen === i" x-cloak
                                    class="absolute left-0 bottom-full mb-1 z-20 w-40 bg-white border border-gray-200 rounded-xl shadow-lg py-1 overflow-hidden">
                                    <button @click="copyRowAs(hex, 'hex', i)" class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                        <span class="font-mono text-[10px] text-gray-400 w-7 shrink-0">#HEX</span> HEX scale
                                    </button>
                                    <button @click="copyRowAs(hex, 'css', i)" class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                        <span class="font-mono text-[10px] text-gray-400 w-7 shrink-0">CSS</span> CSS vars
                                    </button>
                                    <button @click="copyRowAs(hex, 'tailwind', i)" class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                        <span class="font-mono text-[10px] text-gray-400 w-7 shrink-0">TW</span> Tailwind
                                    </button>
                                    <button @click="copyRowAs(hex, 'json', i)" class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                        <span class="font-mono text-[10px] text-gray-400 w-7 shrink-0">{}</span> JSON
                                    </button>
                                </div>
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

            {{-- Harmony tab --}}
            <div x-show="tab === 'harmony'" x-cloak class="space-y-6">
                <p class="text-sm text-gray-500">Pick a seed color and a harmony scheme to generate a palette based on color theory.</p>

                {{-- Seed picker + scheme selector --}}
                <div class="flex flex-wrap items-start gap-5">
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-semibold text-gray-700">Seed color</label>
                        <label class="relative cursor-pointer group">
                            <span class="block w-10 h-10 rounded-xl border-2 border-white shadow-md ring-1 ring-gray-200 group-hover:ring-amber-400 transition-all" :style="`background: ${harmonySeed}`"></span>
                            <input type="color" x-model="harmonySeed" @input.debounce.50ms="generateHarmony()" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                        </label>
                        <span class="font-mono text-sm text-gray-600 w-16" x-text="harmonySeed"></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="s in harmonySchemes" :key="s.id">
                            <button @click="harmonyScheme = s.id; generateHarmony()"
                                class="px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                :class="harmonyScheme === s.id ? 'bg-amber-500 text-white shadow-sm' : 'bg-white border border-gray-300 text-gray-600 hover:border-amber-400'"
                                x-text="s.label"></button>
                        </template>
                    </div>
                </div>

                {{-- 60-30-10 proportion bar --}}
                <div x-show="harmonyScheme === '60-30-10' && harmonyPalette.length === 3" class="space-y-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Color ratio</p>
                    <div class="flex h-8 rounded-xl overflow-hidden shadow-sm border border-gray-200">
                        <div class="flex items-center justify-center text-xs font-bold" style="width:60%"
                            :style="`background:${harmonyPalette[0]?.hex}; color:${harmonyPalette[0]?.hex ? 'white' : 'inherit'}`">60%</div>
                        <div class="flex items-center justify-center text-xs font-bold" style="width:30%"
                            :style="`background:${harmonyPalette[1]?.hex}; color:white`">30%</div>
                        <div class="flex items-center justify-center text-xs font-bold" style="width:10%"
                            :style="`background:${harmonyPalette[2]?.hex}; color:white`">10%</div>
                    </div>
                </div>

                {{-- Harmony palette display --}}
                <div x-show="harmonyPalette.length > 0" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="font-bold text-gray-900"><span x-text="harmonyPalette.length"></span> colors</h2>
                        <div class="relative" @click.outside="harmonyExportOpen = false">
                            <button @click="harmonyExportOpen = !harmonyExportOpen"
                                class="flex items-center gap-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Export
                                <svg class="w-3 h-3 transition-transform" :class="harmonyExportOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <div x-show="harmonyExportOpen" x-cloak
                                class="absolute right-0 top-full mt-1 z-20 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-1 overflow-hidden">
                                <button @click="copyHarmonyAs('hex')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">#HEX</span> HEX list
                                </button>
                                <button @click="copyHarmonyAs('css')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">CSS</span> CSS variables
                                </button>
                                <button @click="copyHarmonyAs('tailwind')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">TW</span> Tailwind config
                                </button>
                                <button @click="copyHarmonyAs('json')" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition-colors text-left">
                                    <span class="font-mono text-xs text-gray-400 w-10 shrink-0">{}</span> JSON
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex rounded-2xl overflow-hidden h-16 border border-gray-200 shadow-sm">
                        <template x-for="color in harmonyPalette" :key="color.hex">
                            <div class="flex-1" :style="`background: ${color.hex}`" :title="color.hex"></div>
                        </template>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="color in harmonyPalette" :key="color.hex">
                            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                <div class="h-20" :style="`background: ${color.hex}`"></div>
                                <div class="p-3 space-y-1.5">
                                    <template x-if="color.role">
                                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider pb-0.5 border-b border-gray-100" x-text="color.role"></div>
                                    </template>
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

                <p class="text-xs text-gray-400">Palettes are generated in your browser using HSL color math. Analogous and Nature schemes rotate around the color wheel; Complementary pairs opposite hues with lightness variants; 60-30-10 applies the triadic rule with usage proportions. Nothing is uploaded.</p>
            </div>
        </div>
    </x-tools.shell>
@endsection
