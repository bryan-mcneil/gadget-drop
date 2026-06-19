@extends('layouts.public')

@section('content')
    <x-tools.shell :title="$toolName" :products="[]" workbench>
        <x-slot:description>
            Crop, resize, rotate, and adjust the colors of any image, right in your browser. Tweak it, undo any time, then export to JPG, PNG, or WebP. Nothing is uploaded to a server.
        </x-slot:description>

        <div x-data="imageEditor('{{ $initialTool ?? 'crop' }}')" @file-loaded="loadImage($event.detail)">
            {{-- Empty state --}}
            <div x-show="!workingSrc" class="max-w-2xl mx-auto">
                <x-tools.drop-zone />
            </div>

            {{-- Editor --}}
            <div x-show="workingSrc" x-cloak
                class="flex flex-col gap-3 lg:grid lg:grid-cols-[64px_240px_minmax(0,1fr)] lg:gap-4 lg:items-start xl:grid-cols-[64px_240px_minmax(0,1fr)_300px]">

                {{-- Icon rail --}}
                <nav class="flex lg:flex-col gap-1.5 overflow-x-auto lg:overflow-visible bg-gray-950 rounded-2xl p-2">
                    <button @click="selectTool('crop')" title="Crop" class="flex items-center justify-center w-12 h-12 rounded-xl flex-shrink-0 transition-colors" :class="activeTool==='crop' ? 'bg-amber-500 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3v13.5h13.5M3 7.5h13.5V21" /></svg>
                    </button>
                    <button @click="selectTool('resize')" title="Resize" class="flex items-center justify-center w-12 h-12 rounded-xl flex-shrink-0 transition-colors" :class="activeTool==='resize' ? 'bg-amber-500 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9m11.25-5.25h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15M3.75 20.25h4.5m-4.5 0v-4.5m0 4.5L9 15" /></svg>
                    </button>
                    <button @click="selectTool('transform')" title="Rotate &amp; flip" class="flex items-center justify-center w-12 h-12 rounded-xl flex-shrink-0 transition-colors" :class="activeTool==='transform' ? 'bg-amber-500 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    </button>
                    <button @click="selectTool('adjust')" title="Adjust colors" class="flex items-center justify-center w-12 h-12 rounded-xl flex-shrink-0 transition-colors" :class="activeTool==='adjust' ? 'bg-amber-500 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    </button>
                </nav>

                {{-- Contextual controls panel (dark) --}}
                <aside class="bg-gray-900 border border-gray-800 rounded-2xl p-4 lg:sticky lg:top-6 text-gray-300">
                    <h2 class="text-sm font-semibold text-gray-100 mb-4" x-text="toolLabel()"></h2>

                    {{-- Crop --}}
                    <div x-show="activeTool==='crop'" class="space-y-3">
                        <label class="text-xs text-gray-400 font-medium">Aspect ratio</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            <template x-for="a in aspects" :key="a.label">
                                <button @click="setAspect(a)" class="py-2 text-xs font-semibold rounded-lg border transition-colors" :class="aspectKey===a.label ? 'border-amber-500 bg-amber-500/15 text-amber-300' : 'border-gray-700 text-gray-300 hover:border-gray-500'" x-text="a.label"></button>
                            </template>
                        </div>
                        <button @click="applyCrop" class="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-400 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm">Apply crop</button>
                    </div>

                    {{-- Resize --}}
                    <div x-show="activeTool==='resize'" class="space-y-3">
                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">Width (px)</label>
                            <input type="number" min="1" :value="rsWidth" @input="onRsWidth($event.target.value)" class="no-spinner w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs text-gray-400 font-medium">Height (px)</label>
                            <input type="number" min="1" :value="rsHeight" @input="onRsHeight($event.target.value)" class="no-spinner w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm text-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400" />
                        </div>
                        <button @click="rsLock = !rsLock" type="button" role="switch" :aria-checked="rsLock" class="w-full flex items-center justify-between py-1">
                            <span class="text-sm text-gray-300">Aspect locked</span>
                            <span class="relative w-10 h-6 rounded-full transition-colors flex-shrink-0" :class="rsLock ? 'bg-amber-500' : 'bg-gray-600'">
                                <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform" :class="rsLock ? 'translate-x-4' : ''"></span>
                            </span>
                        </button>
                        <p class="text-xs text-gray-500">Current: <span x-text="natW"></span> × <span x-text="natH"></span> px</p>
                        <button @click="applyResize" class="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-400 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm">Apply resize</button>
                    </div>

                    {{-- Rotate & flip --}}
                    <div x-show="activeTool==='transform'" class="grid grid-cols-2 gap-2">
                        <button @click="rotate(-90)" class="flex flex-col items-center justify-center gap-1.5 py-3 rounded-lg border border-gray-700 text-gray-300 hover:border-amber-500 hover:text-amber-300 transition-colors text-xs font-medium">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                            Rotate left
                        </button>
                        <button @click="rotate(90)" class="flex flex-col items-center justify-center gap-1.5 py-3 rounded-lg border border-gray-700 text-gray-300 hover:border-amber-500 hover:text-amber-300 transition-colors text-xs font-medium">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" /></svg>
                            Rotate right
                        </button>
                        <button @click="flip('h')" class="flex flex-col items-center justify-center gap-1.5 py-3 rounded-lg border border-gray-700 text-gray-300 hover:border-amber-500 hover:text-amber-300 transition-colors text-xs font-medium">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3v18L3 16.5M21 7.5 16.5 3v18L21 16.5M12 2v20" /></svg>
                            Flip H
                        </button>
                        <button @click="flip('v')" class="flex flex-col items-center justify-center gap-1.5 py-3 rounded-lg border border-gray-700 text-gray-300 hover:border-amber-500 hover:text-amber-300 transition-colors text-xs font-medium">
                            <svg class="w-5 h-5 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3v18L3 16.5M21 7.5 16.5 3v18L21 16.5M12 2v20" /></svg>
                            Flip V
                        </button>
                    </div>

                    {{-- Adjust (color) --}}
                    <div x-show="activeTool==='adjust'" class="space-y-3">
                        <template x-for="ctl in adjControls" :key="ctl.key">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs text-gray-400 font-medium" x-text="ctl.label"></label>
                                    <span class="text-xs font-mono font-semibold" :class="adj[ctl.key] !== 0 ? 'text-amber-400' : 'text-gray-500'" x-text="adj[ctl.key]"></span>
                                </div>
                                <input type="range" :min="ctl.min" :max="ctl.max" step="1" x-model.number="adj[ctl.key]" @input="onAdjust()" class="w-full h-1.5 rounded-full appearance-none cursor-pointer bg-gray-700 accent-amber-500" />
                            </div>
                        </template>
                        <div class="flex gap-2 pt-1">
                            <button @click="resetAdj(); _adjRender()" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-700 text-gray-300 hover:border-gray-500 transition-colors">Reset</button>
                            <button @click="applyAdjust" class="flex-1 flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-400 text-white font-semibold py-2 rounded-lg transition-colors text-sm">Apply</button>
                        </div>
                    </div>
                </aside>

                {{-- Canvas stage (dark) --}}
                <div class="flex flex-col bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden min-h-[440px] lg:min-h-[600px]">
                    {{-- Toolbar --}}
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-gray-900 border-b border-gray-800">
                        <div class="text-xs text-gray-400 font-mono truncate"><span x-text="natW"></span> × <span x-text="natH"></span></div>
                        <div class="flex items-center gap-1">
                            <button @click="undo" :disabled="!canUndo" title="Undo" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-800 disabled:opacity-30 disabled:hover:bg-transparent transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                            </button>
                            <button @click="redo" :disabled="!canRedo" title="Redo" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-800 disabled:opacity-30 disabled:hover:bg-transparent transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" /></svg>
                            </button>
                            <button @click="resetAll" :disabled="!canUndo" title="Reset to original" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-800 disabled:opacity-30 disabled:hover:bg-transparent transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                            </button>
                            <span class="w-px h-5 bg-gray-700 mx-1"></span>
                            <button @click="newImage" class="px-2.5 h-8 rounded-lg text-xs font-medium text-gray-300 hover:bg-gray-800 transition-colors">New</button>
                            <button @click="openSave" class="flex items-center gap-1.5 bg-amber-500 hover:bg-amber-400 text-white font-semibold px-3.5 h-8 rounded-lg transition-colors text-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Export
                            </button>
                        </div>
                    </div>
                    {{-- Stage --}}
                    <div class="relative flex-1 flex items-center justify-center p-4 overflow-auto">
                        {{-- Crop surface --}}
                        <div x-show="activeTool==='crop'" class="max-w-full">
                            <img x-ref="cropImg" alt="Crop area" class="block max-w-full" style="max-height:62vh" />
                        </div>
                        {{-- Adjust surface (live color preview) --}}
                        <div x-show="activeTool==='adjust'" class="max-w-full">
                            <canvas x-ref="adjustCanvas" class="block max-w-full max-h-[62vh] rounded shadow-sm"></canvas>
                        </div>
                        {{-- Preview surface (resize / transform) --}}
                        <div x-show="activeTool!=='crop' && activeTool!=='adjust'" class="max-w-full">
                            <img x-ref="stageImg" :src="workingSrc" alt="Working image"
                                :style="hasAlpha ? 'background-image:linear-gradient(45deg,#4b5563 25%,transparent 25%),linear-gradient(-45deg,#4b5563 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#4b5563 75%),linear-gradient(-45deg,transparent 75%,#4b5563 75%);background-size:16px 16px;background-position:0 0,0 8px,8px -8px,-8px 0' : ''"
                                class="block max-w-full max-h-[62vh] object-contain rounded shadow-sm" />
                        </div>
                    </div>
                </div>

                {{-- Related products (right rail on xl, full-width row below otherwise) --}}
                <div class="mt-2 lg:mt-4 xl:mt-0 lg:col-span-3 xl:col-span-1 xl:col-start-4 xl:row-start-1">
                    <x-tools.sidebar :products="$sidebarProducts" layout="row" />
                </div>
            </div>

            {{-- Un-applied crop guard: Export pressed while a crop box is still pending --}}
            <div x-show="cropWarn" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="cropWarnDismiss()">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cropWarnDismiss()"></div>
                <div class="relative bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl w-full max-w-md p-6 text-white">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-500/15 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-gray-100">Apply your crop first?</h2>
                            <p class="mt-1 text-sm text-gray-400">You've drawn a crop area but haven't pressed <span class="font-semibold text-gray-200">Apply crop</span>. Export now and you'll get the full, uncropped image.</p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-col sm:flex-row sm:justify-end gap-2">
                        <button @click="cropWarnDismiss()" class="px-4 py-2.5 text-sm font-medium rounded-xl border border-gray-700 text-gray-300 hover:border-gray-500 transition-colors">Keep editing</button>
                        <button @click="cropWarnExport()" class="px-4 py-2.5 text-sm font-medium rounded-xl border border-gray-700 text-gray-300 hover:border-gray-500 transition-colors">Export without cropping</button>
                        <button @click="cropWarnApply()" class="px-4 py-2.5 text-sm font-semibold rounded-xl bg-amber-500 hover:bg-amber-400 text-white transition-colors">Apply crop &amp; export</button>
                    </div>
                </div>
            </div>

            <x-tools.save-modal />
        </div>
    </x-tools.shell>
@endsection
