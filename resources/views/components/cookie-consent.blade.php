<div x-data="cookieConsent" x-cloak x-show="visible" x-transition.opacity
    role="dialog" aria-label="Cookie consent"
    class="fixed bottom-0 left-0 right-0 z-[9998] p-4 sm:p-6">
    <div class="max-w-4xl mx-auto bg-white border border-gray-200 rounded-2xl shadow-2xl
                flex flex-col sm:flex-row items-start sm:items-center gap-4 p-5">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-lg select-none">
            🍪
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-900 mb-0.5">We use cookies</p>
            <p class="text-xs text-gray-500 leading-relaxed">
                GadgetDrop uses cookies for advertising (Google AdSense) and affiliate link tracking.
                Non-essential cookies are only set with your consent.
                <a href="{{ route('cookies') }}" wire:navigate class="text-indigo-500 underline hover:text-indigo-700">Cookie Policy</a>
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 w-full sm:w-auto">
            <button @click="reject"
                class="flex-1 sm:flex-none px-4 py-2 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Reject non-essential
            </button>
            <button @click="accept"
                class="flex-1 sm:flex-none px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                Accept all
            </button>
        </div>
    </div>
</div>
