{{-- Emits a `file-loaded` event with { src, name, size, type }. Wrap in a parent
     Alpine component and listen with @file-loaded="handleFile($event.detail)". --}}
<div x-data="toolDropZone" class="space-y-3">
    <div @drop.prevent="onDrop($event)" @dragover.prevent="dragging = true" @dragleave="dragging = false"
        @click="$refs.input.click()"
        class="relative flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed cursor-pointer transition-colors py-14 px-6 text-center select-none"
        :class="dragging ? 'border-amber-400 bg-amber-50' : 'border-gray-300 bg-gray-50 hover:border-amber-400 hover:bg-amber-50'">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center transition-colors" :class="dragging ? 'bg-amber-100 text-amber-600' : 'bg-white text-gray-400 border border-gray-200'">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-700" x-text="dragging ? 'Drop your image here' : 'Drag & drop an image here'"></p>
            <p class="text-xs text-gray-400 mt-1">or click to browse: JPG, PNG, WebP, GIF up to 20 MB</p>
        </div>
        <input x-ref="input" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/bmp,image/tiff" class="hidden" @change="process($event.target.files[0])" />
    </div>

    <div x-show="warning" x-cloak class="flex items-start gap-2 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
        <span x-text="warning"></span>
    </div>
    <div x-show="error" x-cloak class="flex items-start gap-2 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-800">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
        <span x-text="error"></span>
    </div>
</div>
