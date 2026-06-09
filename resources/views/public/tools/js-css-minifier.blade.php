@extends('layouts.public')

@section('content')
    <x-tools.shell title="JS & CSS Minifier" :products="$sidebarProducts">
        <x-slot:description>
            Paste your JavaScript or CSS and click <strong>Minify</strong> to shrink file size instantly. See exactly how much space you saved. Runs entirely in your browser.
        </x-slot:description>

        <div x-data="jsCssMinifier">
            {{-- Tab switcher --}}
            <div class="flex border-b border-gray-200">
                <template x-for="t in ['js','css']" :key="t">
                    <button @click="setTab(t)" class="px-5 py-2.5 text-sm font-semibold transition-colors border-b-2 -mb-px"
                        :class="tab === t ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        x-text="t.toUpperCase()"></button>
                </template>
            </div>

            <template x-if="status">
                <div class="flex items-start gap-3 px-4 py-3 rounded-xl text-sm font-medium mt-4"
                    :class="status.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'">
                    <svg x-show="status.type === 'success'" class="w-5 h-5 flex-shrink-0 mt-0.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <svg x-show="status.type !== 'success'" class="w-5 h-5 flex-shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                    <span x-text="status.message"></span>
                </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Input</label>
                        <span x-show="inputBytes > 0" class="text-xs text-gray-400" x-text="fmt(inputBytes)"></span>
                    </div>
                    <textarea x-model="input" @input="status = null; output = ''" spellcheck="false"
                        :placeholder="tab === 'js' ? 'Paste your JavaScript here...' : 'Paste your CSS here...'"
                        class="w-full h-64 md:min-h-[520px] font-mono text-sm bg-gray-950 text-green-400 caret-green-400 rounded-xl p-4 resize-y border border-gray-800 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 placeholder:text-gray-600"></textarea>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Output</label>
                        <div class="flex items-center gap-2">
                            <span x-show="outputBytes > 0" class="text-xs text-gray-400" x-text="fmt(outputBytes)"></span>
                            <span x-show="savingsPct !== null && savingsPct > 0" class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full" x-text="`−${savingsPct}%`"></span>
                        </div>
                    </div>
                    <textarea readonly x-model="output" placeholder="Minified output will appear here..." spellcheck="false"
                        class="w-full h-64 md:min-h-[520px] font-mono text-sm bg-gray-900 text-gray-300 rounded-xl p-4 resize-y border border-gray-800 focus:outline-none cursor-default placeholder:text-gray-600"></textarea>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 mt-4">
                <button @click="minify" :disabled="loading" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                    <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    <svg x-show="!loading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" /></svg>
                    <span x-text="loading ? 'Minifying…' : 'Minify'"></span>
                </button>
                <button @click="copy" :disabled="!output" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    Copy Output
                </button>
                <button @click="clear" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    Clear
                </button>
            </div>

            <p class="text-xs text-gray-400 pt-4">
                <span class="font-medium text-gray-500">JS:</span> powered by Terser, the same engine used by Vite and Webpack in production.
                <span class="font-medium text-gray-500">CSS:</span> whitespace, comment, and redundant-semicolon removal.
            </p>
        </div>
    </x-tools.shell>
@endsection
