@extends('layouts.public')

@php
    $checker = "background-image: linear-gradient(45deg, #d1d5db 25%, transparent 25%), linear-gradient(-45deg, #d1d5db 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #d1d5db 75%), linear-gradient(-45deg, transparent 75%, #d1d5db 75%); background-size: 20px 20px; background-position: 0 0, 0 10px, 10px -10px, -10px 0px; background-color: #f9fafb;";
@endphp

@section('content')
    <x-tools.shell title="Background Remover" :products="$sidebarProducts">
        <x-slot:description>
            Remove white or solid backgrounds from product images instantly. Shadows are feathered automatically so products still look natural. Runs entirely in your browser, nothing is sent to a server.
        </x-slot:description>

        <div x-data="backgroundRemover" @file-loaded="handleFile($event.detail)" class="space-y-5">
            <template x-if="!image"><div><x-tools.drop-zone /></div></template>

            <template x-if="image">
                <div class="space-y-5">
                    {{-- Before / After --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Original</label>
                                <span x-show="sampling" x-cloak class="text-xs text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full animate-pulse">Click anywhere to sample that color</span>
                            </div>
                            <div class="bg-gray-100 rounded-2xl overflow-hidden border-2 transition-all min-h-[220px] flex items-center justify-center" :class="sampling ? 'border-amber-400 cursor-crosshair' : 'border-transparent'">
                                <img :src="image.src" alt="Original" @click="sampleAt($event)" class="max-w-full max-h-[400px] object-contain select-none" draggable="false" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Result</label>
                                <span x-show="brushMode" x-cloak class="text-xs px-2 py-0.5 rounded-full" :class="brushMode === 'erase' ? 'text-rose-700 bg-rose-50 border border-rose-200' : 'text-emerald-700 bg-emerald-50 border border-emerald-200'" x-text="(brushMode === 'erase' ? 'Erase' : 'Restore') + ' — click & drag on the result'"></span>
                            </div>
                            <div class="rounded-2xl overflow-hidden border-2 transition-all min-h-[220px] flex items-center justify-center" :class="brushMode ? (brushMode === 'erase' ? 'border-rose-400' : 'border-emerald-400') : 'border-transparent'" style="{{ $checker }}">
                                <div x-show="processing" class="flex flex-col items-center gap-3 text-gray-400">
                                    <svg class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                    <span class="text-xs font-medium">Removing background...</span>
                                </div>
                                <canvas x-ref="resultCanvas" x-show="!processing && resultUrl"
                                    @pointerdown="brushDown($event)" @pointermove="brushMove($event)" @pointerup="brushUp()" @pointerleave="brushUp()" @pointercancel="brushUp()"
                                    class="max-w-full max-h-[400px] select-none touch-none" :class="brushMode ? 'cursor-crosshair' : ''" style="max-width:100%; max-height:400px;"></canvas>
                            </div>
                        </div>
                    </div>

                    {{-- Controls --}}
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 space-y-5">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-medium text-gray-700">Background color</span>
                                <div class="w-8 h-8 rounded-lg border-2 border-gray-300 shadow-sm flex-shrink-0" :style="`background-color: ${targetHex}`" :title="targetHex"></div>
                                <span class="text-xs font-mono text-gray-400" x-text="targetHex"></span>
                            </div>
                            <button @click="sampling = !sampling" class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg border transition-colors" :class="sampling ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-gray-300 text-gray-600 hover:border-amber-400 hover:text-amber-700'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z" /></svg>
                                <span x-text="sampling ? 'Sampling... click image' : 'Pick from image'"></span>
                            </button>
                            <button @click="resetWhite" class="text-xs text-gray-400 hover:text-gray-600 underline underline-offset-2 transition-colors">Reset to white</button>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <span class="text-sm font-medium text-gray-700">Tolerance</span>
                                    <span class="ml-2 text-xs text-gray-400">higher catches more shadow and near-white pixels</span>
                                </div>
                                <span class="text-sm font-bold text-amber-600 tabular-nums" x-text="tolerance"></span>
                            </div>
                            <input type="range" min="0" max="120" x-model.number="tolerance" class="w-full h-2 rounded-full appearance-none cursor-pointer bg-gray-200 accent-amber-500" />
                            <div class="flex justify-between text-[10px] text-gray-400 mt-1.5"><span>Precise (exact match only)</span><span>Loose (removes shadows too)</span></div>
                            <div class="flex gap-2 mt-3">
                                <template x-for="[label, val] in presets" :key="label">
                                    <button @click="tolerance = val" class="flex-1 py-1.5 text-xs font-medium rounded-lg border transition-colors" :class="tolerance === val ? 'bg-amber-500 border-amber-500 text-white' : 'bg-gray-50 border-gray-200 text-gray-500 hover:border-amber-300 hover:text-amber-700'" x-text="label"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Touch-up brush: restore product parts the remover ate (white-on-white), or erase leftover background --}}
                    <div x-show="resultUrl && !processing" x-cloak class="bg-white border border-gray-200 rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Touch-up brush</span>
                                <p class="text-xs text-gray-400 mt-0.5">Got white parts of the product removed (like white bars or trim)? <strong>Restore</strong> paints them back. <strong>Erase</strong> wipes any background left behind.</p>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="toggleBrush('restore')" class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg border transition-colors" :class="brushMode === 'restore' ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-white border-gray-300 text-gray-600 hover:border-emerald-400 hover:text-emerald-700'">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.47 2.108 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" /></svg>
                                    Restore
                                </button>
                                <button @click="toggleBrush('erase')" class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg border transition-colors" :class="brushMode === 'erase' ? 'bg-rose-500 border-rose-500 text-white' : 'bg-white border-gray-300 text-gray-600 hover:border-rose-400 hover:text-rose-700'">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m12 21 7.5-7.5a2.121 2.121 0 0 0 0-3l-4.5-4.5a2.121 2.121 0 0 0-3 0L4.5 13.5a2.121 2.121 0 0 0 0 3L7.5 19.5m4.5 1.5H21m-9 0H7.5" /></svg>
                                    Erase
                                </button>
                            </div>
                        </div>
                        <div x-show="brushMode" x-cloak>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Brush size</span>
                                <span class="text-sm font-bold text-amber-600 tabular-nums" x-text="brushSize + ' px'"></span>
                            </div>
                            <input type="range" min="8" max="120" x-model.number="brushSize" class="w-full h-2 rounded-full appearance-none cursor-pointer bg-gray-200 accent-amber-500" />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button @click="apply" :disabled="processing" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                            <svg x-show="processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                            <svg x-show="!processing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                            <span x-text="processing ? 'Processing...' : 'Apply'"></span>
                        </button>
                        <button @click="openSave" :disabled="!resultUrl || processing" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            Save As...
                        </button>
                        <button @click="newImage" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            New Image
                        </button>
                    </div>

                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5">
                        Save as <strong>PNG</strong> or <strong>WebP</strong> to keep the transparent background. JPG does not support transparency and will show a white fill instead.
                    </p>
                </div>
            </template>

            <x-tools.save-modal />
        </div>
    </x-tools.shell>
@endsection
