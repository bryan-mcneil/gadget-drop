<footer class="bg-white border-t border-gray-200 py-8 text-xs text-gray-400">
    <div class="max-w-6xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p>
            © {{ date('Y') }} GadgetDrop.tech ·
            GadgetDrop participates in the Amazon Associates program.
            We earn a small commission on qualifying purchases.
        </p>
        <nav class="flex items-center gap-4 shrink-0">
            <a href="{{ route('tools.index') }}" class="hover:text-amber-600 transition-colors font-medium">Tools</a>
            <a href="{{ route('about') }}"   class="hover:text-gray-600 transition-colors">About</a>
            <a href="{{ route('contact') }}" class="hover:text-gray-600 transition-colors">Contact</a>
            <a href="{{ route('privacy') }}" class="hover:text-gray-600 transition-colors">Privacy Policy</a>
            <a href="{{ route('cookies') }}" class="hover:text-gray-600 transition-colors">Cookie Policy</a>
            <a href="{{ route('terms') }}"   class="hover:text-gray-600 transition-colors">Terms</a>
        </nav>
    </div>
</footer>
