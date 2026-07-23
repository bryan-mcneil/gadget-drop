<footer id="footer" class="relative bg-slate-900 text-gray-400">
    {{-- Signature: the header megamenus' per-section gradient rules, compressed
         into one hairline — the whole site's colour system (brand · deals · news). --}}
    <div class="h-0.5 bg-gradient-to-r from-indigo-500 via-sky-400 to-rose-400"></div>

    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="grid gap-10 md:grid-cols-12">

            {{-- Brand + site-wide prose (lives here, not on the homepage, for humans and crawlers) --}}
            <div class="md:col-span-5">
                <a href="{{ route('home') }}" wire:navigate class="font-extrabold text-xl tracking-tight text-white">
                    Gadget<span class="text-indigo-400">Drop</span>
                </a>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-gray-400">
                    Finds the consumer tech worth buying and tracks what it really costs.
                    Every review is <a href="{{ route('how-we-review') }}" wire:navigate class="text-gray-300 underline decoration-gray-600 underline-offset-2 hover:text-white hover:decoration-gray-400 transition-colors">research-based and price-tracked</a>:
                    who a gadget is for, where it falls short, and whether <a href="{{ route('deals') }}" wire:navigate class="text-gray-300 underline decoration-gray-600 underline-offset-2 hover:text-sky-300 hover:decoration-sky-400 transition-colors">today's price is actually a deal</a>.
                </p>
            </div>

            {{-- Link columns --}}
            <div class="grid grid-cols-3 gap-8 md:col-span-7">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Explore</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('deals') }}" wire:navigate class="text-gray-400 hover:text-sky-300 transition-colors">Deals</a></li>
                        {{-- Footer, not the header: the desktop nav already crams at
                             the md breakpoint (see CLAUDE.md), and /deals + the
                             review-page strip carry the discovery load. --}}
                        <li><a href="{{ route('buy-or-wait.index') }}" wire:navigate class="text-gray-400 hover:text-indigo-300 transition-colors">Buy or Wait</a></li>
                        <li><a href="{{ route('drop-price.index') }}" wire:navigate class="text-gray-400 hover:text-white transition-colors">Drop Price Archive</a></li>
                        {{-- Tools stays a full page load: tools.js only ships on /tools/* and
                             registers its Alpine components on alpine:init, which a
                             wire:navigate visit never re-fires. --}}
                        <li><a href="{{ route('tools.index') }}" class="text-gray-400 hover:text-amber-300 transition-colors">Tools</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Company</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('about') }}" wire:navigate class="text-gray-400 hover:text-white transition-colors">About</a></li>
                        <li><a href="{{ route('how-we-review') }}" wire:navigate class="text-gray-400 hover:text-white transition-colors">How We Review</a></li>
                        <li><a href="{{ route('contact') }}" wire:navigate class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Legal</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('privacy') }}" wire:navigate class="text-gray-400 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="{{ route('cookies') }}" wire:navigate class="text-gray-400 hover:text-white transition-colors">Cookie Policy</a></li>
                        <li><a href="{{ route('terms') }}" wire:navigate class="text-gray-400 hover:text-white transition-colors">Terms</a></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-10 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500">
            <p>© {{ date('Y') }} GadgetDrop.tech</p>
            <p class="text-center sm:text-right">
                As an Amazon Associate we earn from qualifying purchases.
            </p>
        </div>
    </div>
</footer>
