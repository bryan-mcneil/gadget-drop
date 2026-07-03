<footer class="bg-white border-t border-gray-200 py-8 text-xs text-gray-400">
    <div class="max-w-6xl mx-auto px-4">
        {{-- What this site is — site-wide prose for humans and crawlers (lives here, not on the homepage) --}}
        <p class="max-w-2xl mx-auto sm:mx-0 text-center sm:text-left text-gray-500 leading-relaxed">
            <span class="font-semibold text-gray-600">GadgetDrop</span> finds the consumer tech worth buying — and tracks what it really costs.
            Every review is <a href="{{ route('how-we-review') }}" wire:navigate class="underline decoration-gray-300 hover:text-gray-600 hover:decoration-gray-400 transition-colors">research-based and price-tracked</a>:
            who a gadget is for, where it falls short, and whether <a href="{{ route('deals') }}" wire:navigate class="underline decoration-gray-300 hover:text-emerald-600 hover:decoration-emerald-300 transition-colors">today's price is actually a deal</a>.
        </p>
    </div>
    <div class="max-w-6xl mx-auto px-4 mt-5 pt-5 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p>
            © {{ date('Y') }} GadgetDrop.tech ·
            GadgetDrop participates in the Amazon Associates program.
            We earn a small commission on qualifying purchases.
        </p>
        <nav class="flex items-center gap-4 shrink-0">
            {{-- Tools links stay full page loads: tools.js only ships on /tools/* and
                 registers its Alpine components on alpine:init, which a wire:navigate
                 visit never re-fires. --}}
            <a href="{{ route('tools.index') }}" class="hover:text-amber-600 transition-colors font-medium">Tools</a>
            <a href="{{ route('deals') }}" wire:navigate class="hover:text-emerald-600 transition-colors">Deals</a>
            <a href="{{ route('drop-price.index') }}" wire:navigate class="hover:text-gray-600 transition-colors">Drop Price Archive</a>
            <a href="{{ route('about') }}"   wire:navigate class="hover:text-gray-600 transition-colors">About</a>
            <a href="{{ route('how-we-review') }}" wire:navigate class="hover:text-gray-600 transition-colors">How We Review</a>
            <a href="{{ route('contact') }}" wire:navigate class="hover:text-gray-600 transition-colors">Contact</a>
            <a href="{{ route('privacy') }}" wire:navigate class="hover:text-gray-600 transition-colors">Privacy Policy</a>
            <a href="{{ route('cookies') }}" wire:navigate class="hover:text-gray-600 transition-colors">Cookie Policy</a>
            <a href="{{ route('terms') }}"   wire:navigate class="hover:text-gray-600 transition-colors">Terms</a>
        </nav>
    </div>
</footer>
