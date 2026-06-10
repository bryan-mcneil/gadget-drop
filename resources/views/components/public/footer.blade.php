<footer class="bg-white border-t border-gray-200 py-8 text-xs text-gray-400">
    <div class="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
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
            <a href="{{ route('about') }}"   wire:navigate class="hover:text-gray-600 transition-colors">About</a>
            <a href="{{ route('contact') }}" wire:navigate class="hover:text-gray-600 transition-colors">Contact</a>
            <a href="{{ route('privacy') }}" wire:navigate class="hover:text-gray-600 transition-colors">Privacy Policy</a>
            <a href="{{ route('cookies') }}" wire:navigate class="hover:text-gray-600 transition-colors">Cookie Policy</a>
            <a href="{{ route('terms') }}"   wire:navigate class="hover:text-gray-600 transition-colors">Terms</a>
        </nav>
    </div>
</footer>
