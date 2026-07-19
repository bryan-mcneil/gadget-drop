<div class="bg-white border border-gray-200 rounded-2xl p-8">
    @if($status === 'success')
        <div class="text-center space-y-3 py-6">
            <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </div>
            <p class="text-xl font-bold text-gray-900">Message sent</p>
            <p class="text-sm text-gray-500">Thanks. It lands straight in {{ config('site.author.name') }}'s inbox. Expect a reply within 2 business days.</p>
        </div>
    @else
        <form wire:submit="send" class="space-y-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label for="contact-name" class="block text-sm font-semibold text-gray-700 mb-1.5">Your name</label>
                    <input id="contact-name" type="text" wire:model="name" autocomplete="name"
                        class="w-full px-4 py-2.5 rounded-xl text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    @error('name') <p class="mt-1.5 text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="contact-email" class="block text-sm font-semibold text-gray-700 mb-1.5">Email</label>
                    <input id="contact-email" type="email" wire:model="email" autocomplete="email"
                        class="w-full px-4 py-2.5 rounded-xl text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    @error('email') <p class="mt-1.5 text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="contact-topic" class="block text-sm font-semibold text-gray-700 mb-1.5">What's it about?</label>
                <select id="contact-topic" wire:model="topic"
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-gray-900 border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="correction">A correction: something's wrong or out of date</option>
                    <option value="tip">A tip: a product or topic worth covering</option>
                    <option value="partnership">Partnership / business</option>
                    <option value="other">Something else</option>
                </select>
                @error('topic') <p class="mt-1.5 text-red-600 text-xs">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="contact-message" class="block text-sm font-semibold text-gray-700 mb-1.5">Message</label>
                <textarea id="contact-message" wire:model="message" rows="5"
                    placeholder="For corrections: link the article and tell us what's wrong, and we'll fix it and credit the catch if you want."
                    class="w-full px-4 py-2.5 rounded-xl text-sm text-gray-900 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"></textarea>
                @error('message') <p class="mt-1.5 text-red-600 text-xs">{{ $message }}</p> @enderror
            </div>

            {{-- Honeypot — invisible to humans, irresistible to bots --}}
            <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;">
                <label for="contact-company">Company</label>
                <input id="contact-company" type="text" wire:model="company" tabindex="-1" autocomplete="off" />
            </div>

            <div class="flex items-center justify-between gap-4 flex-wrap">
                <p class="text-xs text-gray-400">
                    Read by a real person. See our <a href="{{ route('privacy') }}" wire:navigate class="underline hover:text-gray-600">privacy policy</a>.
                </p>
                <button type="submit" wire:loading.attr="disabled"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-8 py-2.5 rounded-xl transition-colors text-sm disabled:opacity-60">
                    <span wire:loading.remove wire:target="send">Send message</span>
                    <span wire:loading wire:target="send">Sending…</span>
                </button>
            </div>

            @if($status === 'throttled')
                <p class="text-amber-600 text-sm">You've sent a few messages already. Give it an hour and try again, or email us directly.</p>
            @endif
            @if($status === 'error')
                <p class="text-red-600 text-sm">Something went wrong sending your message. Please try again or email us directly.</p>
            @endif
        </form>
    @endif
</div>
