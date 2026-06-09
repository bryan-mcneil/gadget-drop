@extends('layouts.public')

@section('content')
    <x-tools.shell title="Password Generator" :products="$sidebarProducts">
        <x-slot:description>
            Generate cryptographically random passwords using your browser's built-in <code class="text-amber-700 bg-amber-50 px-1 rounded text-xs">crypto.getRandomValues()</code>. Adjust length and character types. Nothing leaves your browser.
        </x-slot:description>

        <div x-data="passwordGenerator" class="space-y-6">
            {{-- Password display --}}
            <div class="bg-gray-950 border border-gray-800 rounded-2xl p-5">
                <div class="flex items-start gap-3">
                    <p class="flex-1 font-mono text-lg text-green-400 tracking-widest break-all leading-relaxed select-all min-h-[2.5rem]">
                        <span x-show="password" x-text="password"></span>
                        <span x-show="!password" class="text-gray-600 text-base">Enable at least one character type</span>
                    </p>
                    <div class="flex-shrink-0 flex flex-col gap-2">
                        <button @click="copy" :disabled="!password" class="flex items-center justify-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg transition-colors text-sm disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                            Copy
                        </button>
                        <button @click="regen" class="flex items-center justify-center gap-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium px-4 py-2 rounded-lg transition-colors text-sm whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                            New
                        </button>
                    </div>
                </div>

                <template x-if="strength">
                    <div class="mt-4 pt-4 border-t border-gray-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-500">Password strength</span>
                            <span class="text-xs font-bold" :class="strength.text" x-text="strength.label"></span>
                        </div>
                        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300" :class="strength.color" :style="`width: ${strength.pct}%`"></div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Length --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-gray-900">Length</h2>
                    <span class="text-2xl font-black text-amber-600" x-text="length"></span>
                </div>
                <input type="range" min="8" max="64" x-model.number="length" class="w-full accent-amber-500" />
                <div class="flex items-center justify-between mt-3">
                    <span class="text-xs text-gray-400">8</span>
                    <div class="flex gap-1">
                        <template x-for="n in presets" :key="n">
                            <button @click="length = n" class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors"
                                :class="length === n ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100'" x-text="n"></button>
                        </template>
                    </div>
                    <span class="text-xs text-gray-400">64</span>
                </div>
            </div>

            {{-- Character types --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-6">
                <h2 class="font-bold text-gray-900 mb-4">Character Types</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach([['upper','Uppercase','A–Z'],['lower','Lowercase','a–z'],['numbers','Numbers','0–9'],['symbols','Symbols','!@#$']] as [$key, $label, $example])
                        <button @click="toggleOpt('{{ $key }}')" class="flex flex-col items-center gap-1.5 p-4 rounded-xl border-2 font-semibold transition-all select-none"
                            :class="opts.{{ $key }} ? 'border-amber-400 bg-amber-50 text-amber-700' : 'border-gray-200 bg-white text-gray-400 hover:border-gray-300'">
                            <span class="text-base font-mono font-bold">{{ $example }}</span>
                            <span class="text-xs font-semibold">{{ $label }}</span>
                            <svg x-show="opts.{{ $key }}" class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            <div x-show="!opts.{{ $key }}" class="w-4 h-4"></div>
                        </button>
                    @endforeach
                </div>
            </div>

            <p class="text-xs text-gray-400">Uses <code class="bg-gray-100 px-1 rounded">crypto.getRandomValues()</code>, a cryptographically secure random number generator built into your browser. Nothing is sent to any server.</p>
        </div>
    </x-tools.shell>
@endsection
