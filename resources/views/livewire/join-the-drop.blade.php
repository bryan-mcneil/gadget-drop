<section class="relative py-20 overflow-hidden" style="background: linear-gradient(135deg, #312e81 0%, #4338ca 45%, #6d28d9 100%)">
    {{-- Concentric decorative rings --}}
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-full border border-white/[0.04] pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[480px] h-[480px] rounded-full border border-white/[0.06] pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[260px] h-[260px] rounded-full border border-white/[0.08] pointer-events-none"></div>

    <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px); background-size: 30px 30px;"></div>

    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
    <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>

    <div class="relative max-w-xl mx-auto px-4 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/15 mb-6">
            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
        </div>

        <h2 class="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-3">Join the Drop</h2>
        <p class="text-indigo-200 text-lg mb-10 leading-relaxed">
            Weekly tech picks, curated just for you.<br />
            Zero spam, unsubscribe anytime.
        </p>

        @if($status === 'success')
            <div class="flex flex-col items-center gap-3">
                <div class="w-16 h-16 rounded-full bg-white/15 border border-white/20 flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </div>
                <p class="text-2xl font-bold text-white mt-1">You're in!</p>
                <p class="text-indigo-200 text-base">Watch your inbox for our weekly drop.</p>
                <a href="{{ route('unsubscribe') }}" class="text-xs text-white/30 hover:text-white/60 transition-colors mt-2 inline-block">Unsubscribe anytime →</a>
            </div>
        @else
            <form wire:submit="subscribe" class="flex gap-2 max-w-md mx-auto">
                <input type="email" wire:model="email" placeholder="your@email.com" required
                    class="flex-1 px-4 py-3 rounded-xl text-sm text-gray-900 placeholder-gray-400 bg-white/95 border-0 focus:outline-none focus:ring-2 focus:ring-white/40 shadow-lg" />
                <button type="submit" wire:loading.attr="disabled"
                    class="flex-shrink-0 px-6 py-3 bg-white text-indigo-700 font-bold text-sm rounded-xl hover:bg-indigo-50 transition-colors disabled:opacity-60 whitespace-nowrap shadow-lg">
                    <span wire:loading.remove wire:target="subscribe">Get the Drop</span>
                    <span wire:loading wire:target="subscribe">Joining…</span>
                </button>
            </form>
        @endif

        @error('email') <p class="mt-4 text-red-300 text-sm">{{ $message }}</p> @enderror
        @if($status === 'duplicate')
            <p class="mt-4 text-indigo-200 text-sm">You're already on the list. Stay tuned!</p>
        @endif
        @if($status === 'error')
            <p class="mt-4 text-red-300 text-sm">Something went wrong. Please try again.</p>
        @endif

        <p class="mt-8 text-xs text-white/30">
            We respect your privacy. Only the latest in tech products, no data selling, ever.
        </p>
    </div>
</section>
