@extends('layouts.public')

@section('content')
    <x-tools.shell title="Image Cropper" :products="$sidebarProducts">
        <x-slot:description>
            Upload an image, crop it to the perfect size, rotate, flip, or go circle for profile pictures. Everything runs in your browser, nothing is sent to a server.
        </x-slot:description>

        <div x-data="imageCropper" @file-loaded="handleFile($event.detail)" class="space-y-5">
            <template x-if="!image"><div><x-tools.drop-zone /></div></template>

            <template x-if="image">
                <div class="space-y-5">
                    {{-- Controls toolbar --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                            <template x-for="preset in aspects" :key="preset.label">
                                <button @click="setAspect(preset)" class="px-3 py-2 text-xs font-semibold transition-colors border-r border-gray-200 last:border-r-0"
                                    :class="aspectKey === preset.label && !circleMode ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'" x-text="preset.label"></button>
                            </template>
                        </div>

                        <button @click="toggleCircle" title="Circle crop" class="flex items-center gap-1.5 px-3 py-2 rounded-lg border text-xs font-semibold transition-colors" :class="circleMode ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /></svg>
                            Circle
                        </button>

                        <div class="w-px h-6 bg-gray-200 mx-1 hidden sm:block"></div>

                        <button @click="rotate(-90)" title="Rotate left" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                        </button>
                        <button @click="rotate(90)" title="Rotate right" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" /></svg>
                        </button>

                        <div class="w-px h-6 bg-gray-200 mx-1 hidden sm:block"></div>

                        <button @click="flip('h')" title="Flip horizontal" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                        </button>
                        <button @click="flip('v')" title="Flip vertical" class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="transform: rotate(90deg)"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                        </button>

                        <div class="w-px h-6 bg-gray-200 mx-1 hidden sm:block"></div>

                        <button @click="resetCrop" class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 text-xs font-medium transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                            Reset
                        </button>
                    </div>

                    {{-- Cropper --}}
                    <div class="rounded-2xl overflow-hidden border border-gray-200 bg-gray-100" :class="circleMode ? 'cropper-circle-mode' : ''">
                        <img x-ref="cropImg" alt="To crop" style="max-height: 560px; width: 100%; display: block;" />
                    </div>

                    <p x-show="circleMode" x-cloak class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5">
                        Circle mode: the crop area is square. The downloaded image will be a circle with a transparent background (save as PNG for best results).
                    </p>

                    <div class="flex flex-wrap gap-3">
                        <button @click="openSave" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            Save As...
                        </button>
                        <button @click="newImage" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            New Image
                        </button>
                    </div>

                    <p class="text-xs text-gray-400">Tip: need to convert the format too? <a href="{{ route('tools.image-converter') }}" class="text-amber-600 hover:text-amber-700 font-medium">Try the Image Converter</a></p>
                </div>
            </template>

            <x-tools.save-modal />
        </div>
    </x-tools.shell>
@endsection
