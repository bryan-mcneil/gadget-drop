@extends('layouts.public')

@section('content')
    <x-tools.shell title="DOCX to PDF Converter" :products="$sidebarProducts" workbench>
        <x-slot:description>
            Drop in a Word document and get a real PDF back &mdash; with selectable, searchable text, not a picture of your pages. The conversion runs entirely in this browser tab, so your document is never uploaded anywhere.
        </x-slot:description>

        <div x-data="docxToPdf" class="space-y-6">

            {{-- ── Error / notice banners ────────────────────────────── --}}
            <div x-show="error" x-cloak class="flex items-start gap-2.5 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-800">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                <span x-text="error"></span>
            </div>

            {{-- ── Step 1: pick a file ───────────────────────────────── --}}
            <template x-if="stage === 'idle'">
                <div class="max-w-2xl mx-auto">
                    <div @drop.prevent="onDrop($event)" @dragover.prevent="dragging = true" @dragleave="dragging = false"
                        @click="$refs.fileInput.click()"
                        class="relative flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed cursor-pointer transition-colors py-16 px-6 text-center select-none"
                        :class="dragging ? 'border-amber-400 bg-amber-50' : 'border-gray-300 bg-gray-50 hover:border-amber-400 hover:bg-amber-50'">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center transition-colors" :class="dragging ? 'bg-amber-100 text-amber-600' : 'bg-white text-gray-400 border border-gray-200'">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-700" x-text="dragging ? 'Drop your document here' : 'Drag & drop a Word document here'"></p>
                            <p class="text-xs text-gray-500 mt-1">or click to browse &mdash; .docx up to 30 MB</p>
                        </div>
                        <input x-ref="fileInput" type="file" class="hidden"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            @change="pick($event.target.files[0]); $event.target.value = ''" />
                    </div>

                    <div class="mt-5 grid sm:grid-cols-3 gap-3 text-center">
                        <div class="px-3 py-3 rounded-xl bg-white border border-gray-200">
                            <p class="text-xs font-semibold text-gray-700">Nothing is uploaded</p>
                            <p class="text-xs text-gray-500 mt-0.5">Your file never leaves this device</p>
                        </div>
                        <div class="px-3 py-3 rounded-xl bg-white border border-gray-200">
                            <p class="text-xs font-semibold text-gray-700">Real text, not a screenshot</p>
                            <p class="text-xs text-gray-500 mt-0.5">Searchable and copy-pasteable</p>
                        </div>
                        <div class="px-3 py-3 rounded-xl bg-white border border-gray-200">
                            <p class="text-xs font-semibold text-gray-700">No sign-up, no watermark</p>
                            <p class="text-xs text-gray-500 mt-0.5">No daily conversion limit</p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ── Step 2: preview + settings ────────────────────────── --}}
            <template x-if="stage !== 'idle'">
                <div class="lg:grid lg:grid-cols-[1fr_320px] lg:gap-6 space-y-6 lg:space-y-0">

                    {{-- Preview --}}
                    <div class="min-w-0">
                        <div class="bg-gray-950 rounded-2xl overflow-hidden border border-gray-800">
                            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-800">
                                <span class="text-xs text-gray-400 font-mono truncate min-w-0" x-text="file?.name"></span>
                                <span class="text-xs text-gray-500 flex-shrink-0" x-show="stage === 'ready'" x-cloak>
                                    <span x-text="pages"></span> <span x-text="pages === 1 ? 'page' : 'pages'"></span> &middot; <span x-text="fmtBytes(pdfSize)"></span>
                                </span>
                            </div>
                            <div class="relative">
                                <template x-if="previewUrl">
                                    <iframe :src="previewUrl" title="PDF preview" class="w-full h-[62vh] min-h-[420px] bg-gray-900 border-0"></iframe>
                                </template>
                                <template x-if="!previewUrl">
                                    <div class="w-full h-[62vh] min-h-[420px]"></div>
                                </template>

                                {{-- Working overlay (keeps the old preview in place while re-rendering) --}}
                                <div x-show="working" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-gray-950/80 backdrop-blur-sm">
                                    <svg class="w-7 h-7 text-amber-400 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4Z"></path></svg>
                                    <p class="text-sm text-gray-300 font-medium">Building your PDF&hellip;</p>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Preview not loading? Some mobile browsers can't display a PDF inline &mdash; downloading still works.</p>
                    </div>

                    {{-- Settings rail --}}
                    <div class="space-y-4 min-w-0">
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 space-y-4">
                            <h2 class="text-sm font-semibold text-gray-700">Page setup</h2>

                            <div class="space-y-1">
                                <label for="dp-size" class="text-xs text-gray-500 font-medium">Page size</label>
                                <select id="dp-size" x-model="settings.pageSize" @change="regenerate()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                    <option value="letter">Letter (8.5 &times; 11 in)</option>
                                    <option value="a4">A4 (210 &times; 297 mm)</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label for="dp-orientation" class="text-xs text-gray-500 font-medium">Orientation</label>
                                <select id="dp-orientation" x-model="settings.orientation" @change="regenerate()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                    <option value="p">Portrait</option>
                                    <option value="l">Landscape</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label for="dp-margin" class="text-xs text-gray-500 font-medium">Margins</label>
                                <select id="dp-margin" x-model="settings.margin" @change="regenerate()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                    <option value="narrow">Narrow (0.5 in)</option>
                                    <option value="normal">Normal (1 in)</option>
                                    <option value="wide">Wide (1.25 in)</option>
                                </select>
                            </div>

                            <label class="flex items-center gap-2.5 pt-1 cursor-pointer">
                                <input type="checkbox" x-model="settings.pageNumbers" @change="regenerate()" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
                                <span class="text-sm text-gray-700">Add page numbers</span>
                            </label>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-2xl p-5 space-y-4">
                            <h2 class="text-sm font-semibold text-gray-700">Type</h2>

                            <div class="space-y-1">
                                <label for="dp-font" class="text-xs text-gray-500 font-medium">Font</label>
                                <select id="dp-font" x-model="settings.font" @change="regenerate()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                    <option value="helvetica">Helvetica (sans-serif)</option>
                                    <option value="times">Times (serif)</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label for="dp-fontsize" class="text-xs text-gray-500 font-medium">Body text size</label>
                                <select id="dp-fontsize" x-model="settings.fontSize" @change="regenerate()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                    <option value="9">9 pt</option>
                                    <option value="10">10 pt</option>
                                    <option value="11">11 pt</option>
                                    <option value="12">12 pt</option>
                                    <option value="14">14 pt</option>
                                </select>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-col gap-3">
                            <button @click="download()" :disabled="stage !== 'ready'"
                                class="flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 disabled:hover:bg-amber-500 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Download PDF
                            </button>
                            <button @click="reset()" class="flex items-center justify-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                Convert another
                            </button>
                        </div>

                        {{-- Honest warnings about what the conversion couldn't carry over --}}
                        <div x-show="notice" x-cloak class="px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-xs text-gray-600" x-text="notice"></div>
                        <template x-for="warning in warnings" :key="warning">
                            <div class="flex items-start gap-2 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                <span x-text="warning"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- ── What carries over ─────────────────────────────────── --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-6">
                <h2 class="text-base font-bold text-gray-900 mb-3">What carries over</h2>
                <div class="grid sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                    <ul class="space-y-1.5 text-gray-600">
                        <li class="flex gap-2"><span class="text-green-600 font-bold">&check;</span> Headings, paragraphs, and page breaks</li>
                        <li class="flex gap-2"><span class="text-green-600 font-bold">&check;</span> Bold, italic, underline, strikethrough</li>
                        <li class="flex gap-2"><span class="text-green-600 font-bold">&check;</span> Bulleted and numbered lists, including nested ones</li>
                        <li class="flex gap-2"><span class="text-green-600 font-bold">&check;</span> Tables, with the header row repeated across pages</li>
                    </ul>
                    <ul class="space-y-1.5 text-gray-600">
                        <li class="flex gap-2"><span class="text-green-600 font-bold">&check;</span> Embedded images and clickable hyperlinks</li>
                        <li class="flex gap-2"><span class="text-green-600 font-bold">&check;</span> Block quotes, horizontal rules, super/subscript</li>
                        <li class="flex gap-2"><span class="text-gray-400 font-bold">&ndash;</span> Word's exact fonts, colours, and column layout are re-flowed, not mirrored</li>
                        <li class="flex gap-2"><span class="text-gray-400 font-bold">&ndash;</span> Headers, footers, footnotes, charts, and SmartArt are dropped</li>
                    </ul>
                </div>
                <p class="text-xs text-gray-500 mt-4">
                    Built for Latin-script documents: the standard PDF fonts have no glyphs for Chinese, Japanese, Arabic, or Cyrillic text, and this tool tells you rather than quietly mangling it. Need a pixel-perfect copy of a heavily designed Word file? Use Word's own <span class="font-medium text-gray-600">File &rarr; Save as PDF</span> &mdash; nothing in a browser matches it.
                </p>
            </div>
        </div>
    </x-tools.shell>
@endsection
