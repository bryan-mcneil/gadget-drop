@extends('layouts.public')

@section('content')
    {{-- Category hero --}}
    <section class="relative overflow-hidden min-h-[300px] md:min-h-[360px] flex items-center"
        style="background: linear-gradient(135deg, #0d0d2b 0%, #0f0a1e 50%, #0a0f1e 100%)">
        <div class="absolute inset-0 pointer-events-none" style="background-image: radial-gradient(circle, rgba(99,102,241,0.18) 1px, transparent 1px); background-size: 28px 28px;"></div>
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]" style="background-image: repeating-linear-gradient(45deg, white 0px, white 1px, transparent 0px, transparent 50%); background-size: 20px 20px;"></div>
        <div class="absolute right-0 top-1/2 -translate-y-1/2 w-80 h-80 rounded-full bg-indigo-600/10 blur-3xl pointer-events-none"></div>
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-indigo-500/40 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>

        <div class="relative z-10 max-w-6xl mx-auto px-4 py-14 w-full">
            <nav class="flex items-center gap-1.5 text-xs text-gray-500 mb-5">
                <a href="{{ route('home') }}" wire:navigate class="hover:text-indigo-400 transition-colors">Home</a>
                <svg class="w-3 h-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                <span class="text-gray-300 font-medium">{{ $category['name'] }}</span>
            </nav>
            <span class="inline-flex items-center gap-1.5 text-indigo-400 text-xs font-bold uppercase tracking-[0.15em] mb-3">
                <span class="w-4 h-px bg-indigo-400"></span>
                Category
            </span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white leading-tight max-w-2xl">{{ $category['name'] }}</h1>
            @if(!empty($category['description']))
                <p class="mt-4 text-gray-300 text-base md:text-lg leading-relaxed max-w-xl">{{ $category['description'] }}</p>
            @endif
            @if($posts->total() > 0)
                <div class="mt-6 inline-flex items-center gap-2 bg-white/8 backdrop-blur-sm border border-white/10 text-white text-xs font-semibold px-4 py-2 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                    {{ $posts->total() }} drop{{ $posts->total() !== 1 ? 's' : '' }}
                </div>
            @endif
        </div>
    </section>

    <div class="relative">
        <div class="h-px bg-gradient-to-r from-transparent via-indigo-200 to-transparent"></div>

        <div class="max-w-6xl mx-auto px-4 py-12 grid grid-cols-1 lg:grid-cols-4 gap-10">
            <main class="lg:col-span-3">
                @if($posts->total() === 0)
                    <div class="flex flex-col items-center justify-center py-24 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z" /></svg>
                        </div>
                        <p class="text-lg font-bold text-gray-900">No drops yet</p>
                        <p class="text-sm text-gray-400 mt-1 mb-6">Check back soon, we're always adding new picks.</p>
                        <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-700 font-semibold transition-colors">← Back to Home</a>
                    </div>
                @else
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center gap-3">
                            <span class="w-1 h-7 rounded-full bg-gradient-to-b from-indigo-500 to-purple-500"></span>
                            <div>
                                <h2 class="text-2xl font-extrabold text-gray-900 leading-none">{{ $category['name'] }}</h2>
                                <p class="text-xs text-gray-400 mt-0.5 tracking-wide">{{ $posts->total() }} post{{ $posts->total() !== 1 ? 's' : '' }} in this category</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        @foreach($posts as $post)
                            <x-post-card :post="$post" />
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $posts->onEachSide(1)->links() }}
                    </div>
                @endif
            </main>

            <aside class="space-y-5">
                <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                    <h3 class="flex items-center gap-2 font-bold text-gray-800 text-xs uppercase tracking-widest mb-4">
                        <span class="w-3 h-px bg-indigo-400"></span>
                        Browse Categories
                    </h3>
                    <ul class="space-y-0.5">
                        @foreach($categories as $cat)
                            <li>
                                <a href="{{ route('category', $cat['slug']) }}" wire:navigate
                                    class="flex items-center justify-between group py-1.5 px-2 rounded-lg text-sm transition-colors {{ $cat['slug'] === $category['slug'] ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50' }}">
                                    <span>{{ $cat['name'] }}</span>
                                    <svg class="w-3.5 h-3.5 flex-shrink-0 transition-colors {{ $cat['slug'] === $category['slug'] ? 'text-indigo-400' : 'text-gray-300 group-hover:text-indigo-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <p class="text-xs text-gray-400 px-1">#ad #commissionsearned. As an Amazon Associate we earn from qualifying purchases.</p>
            </aside>
        </div>
    </div>
@endsection
