{{-- Renders inside a parent Alpine component that includes saveModalState() mixin
     (modalOpen, sm_* state, smSetFormat, smSetQuality, smDownload, closeSave). --}}
<div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeSave"></div>

    <div class="relative bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md text-white overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
            <h2 class="text-base font-semibold">Save Image</h2>
            <button @click="closeSave" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="px-6 pt-5 pb-2 space-y-2">
            <template x-for="f in sm_formats" :key="f.id">
                <button @click="smSetFormat(f.id)" class="w-full flex items-center gap-4 px-4 py-3.5 rounded-xl border transition-all text-left"
                    :class="sm_format === f.id ? 'border-amber-500 bg-amber-500/10' : 'border-gray-700 bg-gray-800 hover:border-gray-500'">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0" :class="sm_format === f.id ? 'bg-amber-500 text-white' : 'bg-gray-700 text-gray-300'" x-text="f.label"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium" x-text="f.label"></span>
                            <span x-show="f.recommended" class="text-[10px] font-bold uppercase tracking-wider bg-amber-500 text-white px-1.5 py-0.5 rounded">Recommended</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="f.description"></p>
                    </div>
                    <svg x-show="sm_format === f.id" class="w-4 h-4 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>
                </button>
            </template>
        </div>

        <div x-show="sm_selected().hasQuality" class="px-6 py-4 border-t border-gray-700/50 mt-2">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-gray-200">Quality</span>
                <span class="text-sm font-bold text-amber-400" x-text="`${sm_quality}%`"></span>
            </div>
            <input type="range" min="10" max="100" step="1" :value="sm_quality" @input="smSetQuality(Number($event.target.value))" class="w-full h-2 rounded-full appearance-none cursor-pointer bg-gray-700 accent-amber-500" />
            <div class="flex justify-between text-[10px] text-gray-500 mt-1.5"><span>Smaller file</span><span>Best quality</span></div>
            <div class="flex gap-2 mt-3">
                @foreach(['Low' => 60, 'Med' => 80, 'High' => 90] as $label => $val)
                    <button @click="smSetQuality({{ $val }})" class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition-colors" :class="sm_quality === {{ $val }} ? 'bg-amber-500 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="px-6 py-3 border-t border-gray-700/50">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-400">Estimated size</span>
                <span class="font-semibold transition-colors" :class="(sm_estimating && sm_outputSize === null) ? 'text-gray-500' : (sm_estimating ? 'text-gray-400' : 'text-green-400')" x-text="(sm_estimating && sm_outputSize === null) ? 'Calculating…' : smFmtBytes(sm_outputSize)"></span>
            </div>
        </div>

        <div class="px-6 pb-6 pt-3 flex gap-3">
            <button @click="closeSave" class="flex-1 py-2.5 rounded-xl border border-gray-600 text-gray-300 hover:border-gray-400 hover:text-white transition-colors text-sm font-medium">Cancel</button>
            <button @click="smDownload" :disabled="sm_downloading || sm_estimating" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:bg-amber-700 disabled:text-amber-300 text-white font-semibold text-sm transition-colors">
                <svg x-show="sm_downloading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                <svg x-show="!sm_downloading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                <span x-text="sm_downloading ? 'Saving…' : `Save as ${sm_selected().label}`"></span>
            </button>
        </div>
    </div>
</div>
