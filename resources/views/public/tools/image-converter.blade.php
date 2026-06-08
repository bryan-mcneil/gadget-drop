@extends('layouts.public')

@section('content')
    <x-tools.shell title="Image Converter" :products="$sidebarProducts">
        <x-slot:description>
            Upload an image, resize it, then save as JPG, PNG, or WebP. Everything runs in your browser, nothing is sent to a server.
        </x-slot:description>

        <div x-data="imageConverter" @file-loaded="handleFile($event.detail)" class="space-y-6">
            <template x-if="!image"><div><x-tools.drop-zone /></div></template>

            <template x-if="image">
                <div class="space-y-6">
                    {{-- Preview --}}
                    <div class="bg-gray-950 rounded-2xl overflow-hidden border border-gray-800">
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-800">
                            <span class="text-xs text-gray-400 font-mono truncate max-w-[60%]" x-text="image.name"></span>
                            <span class="text-xs text-gray-500" x-text="fmtBytes(image.size)"></span>
                        </div>
                        <div class="p-4 flex items-center justify-center min-h-[280px] max-h-[520px] overflow-hidden">
                            <img x-ref="preview" :src="image.src" alt="Preview" class="max-w-full max-h-[480px] rounded-xl object-contain" />
                        </div>
                    </div>

                    {{-- Resize --}}
                    <div class="bg-white border border-gray-200 rounded-2xl p-5">
                        <h2 class="text-sm font-semibold text-gray-700 mb-4">Resize (optional)</h2>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 space-y-1">
                                <label class="text-xs text-gray-500 font-medium">Width (px)</label>
                                <input type="number" min="1" :value="width" @input="onWidth($event.target.value)" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400" />
                            </div>
                            <button @click="aspectLocked = !aspectLocked" :title="aspectLocked ? 'Unlock aspect ratio' : 'Lock aspect ratio'" class="mt-5 w-9 h-9 rounded-lg border flex items-center justify-center flex-shrink-0 transition-colors" :class="aspectLocked ? 'border-amber-400 bg-amber-50 text-amber-600' : 'border-gray-300 bg-white text-gray-400 hover:border-gray-400'">
                                <svg x-show="aspectLocked" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                <svg x-show="!aspectLocked" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 0 1 4.5-4.5 4.5 4.5 0 0 1 4.5 4.5v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                            </button>
                            <div class="flex-1 space-y-1">
                                <label class="text-xs text-gray-500 font-medium">Height (px)</label>
                                <input type="number" min="1" :value="height" @input="onHeight($event.target.value)" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400" />
                            </div>
                        </div>
                        <p x-show="natW > 0" class="text-xs text-gray-400 mt-3">
                            Original: <span x-text="natW"></span> x <span x-text="natH"></span> px
                            <span x-show="aspectLocked" class="ml-2 text-amber-600 font-medium">Aspect ratio locked</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button @click="openSave" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            Save As...
                        </button>
                        <button @click="reset" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            New Image
                        </button>
                    </div>

                    <p class="text-xs text-gray-400">Tip: need to crop first? <a href="{{ route('tools.image-cropper') }}" class="text-amber-600 hover:text-amber-700 font-medium">Try the Image Cropper</a></p>
                </div>
            </template>

            <x-tools.save-modal />
        </div>
    </x-tools.shell>
@endsection
