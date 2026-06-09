@extends('layouts.public')

@section('content')
    <x-tools.shell title="Base64 Encoder / Decoder" :products="$sidebarProducts">
        <x-slot:description>
            Encode text to Base64 or decode a Base64 string back to plain text. Switch to <strong>File</strong> mode to convert any file to a Base64 data URL, useful for embedding images in CSS or HTML. Everything runs in your browser.
        </x-slot:description>

        <div x-data="base64Tool">
            {{-- Mode tabs --}}
            <div class="flex border-b border-gray-200">
                <button @click="switchMode('text')" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px" :class="mode === 'text' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'">Text</button>
                <button @click="switchMode('file')" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors -mb-px" :class="mode === 'file' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'">File → Base64</button>
            </div>

            <template x-if="error">
                <div class="flex items-start gap-3 px-4 py-3 rounded-xl text-sm font-medium bg-red-50 text-red-800 border border-red-200 mt-4">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    <span x-text="error"></span>
                </div>
            </template>

            {{-- Text mode --}}
            <div x-show="mode === 'text'" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Input</label>
                    <textarea x-model="input" @input="error = null; output = ''" spellcheck="false"
                        placeholder="Paste text to encode, or a Base64 string to decode..."
                        class="w-full min-h-[10rem] font-mono text-sm bg-gray-950 text-gray-200 caret-amber-400 rounded-xl p-4 resize-y border border-gray-800 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 placeholder:text-gray-600"></textarea>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button @click="encode" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        Encode to Base64
                    </button>
                    <button @click="decode" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-amber-400 text-gray-700 font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                        Decode from Base64
                    </button>
                    <button @click="clear" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        Clear
                    </button>
                </div>
                <div x-show="output">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-semibold text-gray-700">Output</label>
                        <button @click="copyOutput" class="flex items-center gap-1.5 text-xs font-medium text-amber-600 hover:text-amber-700 transition-colors">
                            Copy
                        </button>
                    </div>
                    <textarea readonly x-model="output" class="w-full min-h-[10rem] font-mono text-sm bg-gray-950 text-amber-400 rounded-xl p-4 resize-y border border-gray-800 focus:outline-none"></textarea>
                </div>
            </div>

            {{-- File mode --}}
            <div x-show="mode === 'file'" x-cloak class="space-y-4 mt-4">
                <div @click="$refs.fileInput.click()" class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition-colors py-14 px-6 text-center select-none">
                    <div class="w-14 h-14 rounded-2xl bg-white border border-gray-200 flex items-center justify-center text-gray-400">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700" x-text="fileName ? fileName : 'Click to choose any file'"></p>
                        <p class="text-xs text-gray-400 mt-1">Image, PDF, font, or any binary file</p>
                    </div>
                    <input x-ref="fileInput" type="file" class="hidden" @change="handleFile" />
                </div>
                <div x-show="output">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-semibold text-gray-700">Base64 output</label>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-400" x-text="`${output.length.toLocaleString()} chars`"></span>
                            <button @click="copyOutput" class="flex items-center gap-1.5 text-xs font-medium text-amber-600 hover:text-amber-700 transition-colors">
                                Copy
                            </button>
                        </div>
                    </div>
                    <textarea readonly x-model="output" rows="6" class="w-full font-mono text-xs bg-gray-950 text-amber-400 rounded-xl p-4 resize-y border border-gray-800 focus:outline-none"></textarea>
                    <p class="mt-2 text-xs text-gray-400">To use in HTML/CSS, prefix with the data URL header: <code class="bg-gray-100 px-1 rounded">data:image/png;base64,…</code></p>
                </div>
            </div>

            <p class="text-xs text-gray-400 pt-4">All encoding and decoding happens entirely in your browser. No text or files are sent to any server.</p>
        </div>
    </x-tools.shell>
@endsection
