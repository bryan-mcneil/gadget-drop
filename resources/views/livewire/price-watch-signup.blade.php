<div class="border border-indigo-100 rounded-xl px-5 py-4 bg-indigo-50/40">
    @if($status === 'pending')
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900">Check your email</p>
                <p class="text-xs text-gray-500 mt-0.5">Click the confirmation link we just sent and we'll watch the price for the rest of your return window. The link expires in 48 hours.</p>
            </div>
        </div>
    @elseif($status === 'duplicate')
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900">You're already watching this one</p>
                <p class="text-xs text-gray-500 mt-0.5">We'll email you if the price drops enough. If you haven't confirmed yet, check your inbox for the verification link.</p>
            </div>
        </div>
    @else
        <div class="flex items-center gap-2 text-xs font-bold text-gray-500 uppercase tracking-wider">
            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
            Already bought it?
        </div>
        <p class="mt-1.5 text-sm text-gray-600">
            We'll watch this price for the rest of your {{ $windowDays }}-day return window and email you once if it drops enough to be worth a return &amp; rebuy. Compared against our tracked price for your purchase date.
        </p>

        <form wire:submit="startWatch" class="mt-3">
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="email" wire:model="email" placeholder="your@email.com" autocomplete="email" aria-label="Email address" dusk="watch-email"
                    class="flex-1 min-w-0 px-3.5 py-2 rounded-lg text-sm text-gray-900 placeholder-gray-400 bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                <input type="date" wire:model="purchased_on" aria-label="Purchase date"
                    max="{{ today()->toDateString() }}" min="{{ today()->subDays($windowDays)->toDateString() }}"
                    class="sm:w-40 px-3.5 py-2 rounded-lg text-sm text-gray-900 bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                <button type="submit" wire:loading.attr="disabled" dusk="watch-submit"
                    class="flex-shrink-0 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg transition-colors disabled:opacity-60 whitespace-nowrap">
                    <span wire:loading.remove wire:target="startWatch">Watch this price</span>
                    <span wire:loading wire:target="startWatch">Setting up…</span>
                </button>
            </div>

            {{-- Honeypot — invisible to humans, irresistible to bots --}}
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;">
                <label for="watch-company-{{ $productId }}">Company</label>
                <input id="watch-company-{{ $productId }}" type="text" wire:model="company" tabindex="-1" autocomplete="off" />
            </div>

            @error('email') <p class="mt-2 text-red-600 text-xs">{{ $message }}</p> @enderror
            @error('purchased_on') <p class="mt-2 text-red-600 text-xs">Pick the date you bought it — within the last {{ $windowDays }} days.</p> @enderror
            @if($status === 'throttled')
                <p class="mt-2 text-amber-600 text-xs">That's a few watches in a row — give it an hour and try again.</p>
            @endif
            @if($status === 'error')
                <p class="mt-2 text-red-600 text-xs">Something went wrong setting up the watch. Please try again.</p>
            @endif

            <p class="mt-2 text-[11px] text-gray-400">
                One email max, only if it drops. Your address is deleted after the window closes. <a href="{{ route('privacy') }}" wire:navigate class="underline hover:text-gray-600">Privacy</a>
            </p>
        </form>
    @endif
</div>
