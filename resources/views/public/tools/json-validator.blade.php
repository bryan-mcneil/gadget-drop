@extends('layouts.public')

@section('content')
    <x-tools.shell title="JSON Validator" :products="$sidebarProducts">
        <x-slot:description>
            Paste your JSON below and click <strong>Validate &amp; Format</strong>. Errors are shown with a plain-English explanation. Everything runs in your browser, nothing is sent to a server.
        </x-slot:description>

        <div x-data="jsonValidator">
            <div class="relative">
                <textarea x-model="input" @input="status = null" spellcheck="false"
                    placeholder="Paste your JSON here..."
                    class="w-full min-h-[30rem] font-mono text-sm bg-gray-950 text-green-400 caret-green-400 rounded-xl p-4 resize-y border focus:outline-none placeholder:text-gray-600 transition-[box-shadow,border-color] duration-300"
                    :class="status
                        ? (status.type === 'success'
                            ? 'border-green-400 ring-2 ring-green-400/70 shadow-[0_0_22px_-3px_rgba(34,197,94,0.6)]'
                            : 'border-red-400 ring-2 ring-red-400/70 shadow-[0_0_22px_-3px_rgba(239,68,68,0.6)]')
                        : 'border-gray-800 focus:ring-2 focus:ring-amber-400 focus:border-amber-400'"></textarea>

                {{-- Status pill: floats over the textarea (no layout shift) for both success and error --}}
                <template x-if="status">
                    <div class="absolute top-3 right-3 max-w-[calc(100%-1.5rem)] flex items-start gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm pointer-events-none"
                        :class="status.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'">
                        <svg x-show="status.type === 'success'" class="w-4 h-4 flex-shrink-0 mt-px text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <svg x-show="status.type !== 'success'" class="w-4 h-4 flex-shrink-0 mt-px text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                        <span x-text="status.message"></span>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-3 mt-4">
                <button @click="validate" class="flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    Validate &amp; Format
                </button>
                <button @click="copy" :disabled="!input" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    Copy
                </button>
                <button @click="clear" class="flex items-center gap-2 bg-white border border-gray-300 hover:border-gray-400 text-gray-700 font-medium px-5 py-2.5 rounded-lg transition-colors text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    Clear
                </button>
            </div>

            <div class="text-xs text-gray-400 space-y-1 pt-4">
                <p><span class="font-medium text-gray-500">Tip:</span> Validate &amp; Format will also pretty-print minified JSON, making it easy to read.</p>
            </div>
        </div>
    </x-tools.shell>
@endsection
