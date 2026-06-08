<div class="w-full max-w-md">
    @if($result === 'success')
        <div class="text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-2">
                <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900">You're unsubscribed</h1>
            <p class="text-gray-500 text-sm leading-relaxed">
                You've been removed from the GadgetDrop list.<br />
                No more emails from us, we promise.
            </p>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 mt-4 text-sm text-indigo-600 hover:text-indigo-700 font-semibold transition-colors">← Back to GadgetDrop</a>
        </div>
    @else
        <div>
            <div class="text-center mb-8">
                <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Unsubscribe</h1>
                <p class="text-gray-500 text-sm">Enter the email address you signed up with and we'll remove it immediately.</p>
            </div>

            <form wire:submit="remove" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-8 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                    <input type="email" wire:model="email" placeholder="your@email.com" required autofocus
                        class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if($result === 'not_found')
                    <p class="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5">That email isn't on our list; you may already be unsubscribed.</p>
                @endif
                @if($result === 'error')
                    <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5">Something went wrong. Please try again.</p>
                @endif

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-semibold text-sm rounded-lg transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="remove">Unsubscribe me</span>
                    <span wire:loading wire:target="remove">Removing…</span>
                </button>
            </form>

            <p class="text-center mt-6 text-xs text-gray-400">
                Changed your mind?
                <a href="{{ route('home') }}" class="text-indigo-600 hover:underline font-medium">Go back to GadgetDrop</a>
            </p>
        </div>
    @endif
</div>
