@props(['post'])

@php
    $rating = $post['rating'] ?? null;
    $rating = is_numeric($rating) ? (float) $rating : null;
    $pros   = array_values(array_filter($post['pros'] ?? [], fn ($v) => filled($v)));
    $cons   = array_values(array_filter($post['cons'] ?? [], fn ($v) => filled($v)));
    $hasVerdict = ($rating !== null && $rating > 0) || count($pros) > 0 || count($cons) > 0;
@endphp

@if($hasVerdict)
    <section class="mt-10 pt-8 border-t border-gray-100" aria-label="The verdict">
        <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 to-white p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">The Verdict</h2>
                @if($rating !== null && $rating > 0)
                    @php
                        $full = (int) floor($rating);
                        $half = ($rating - $full) >= 0.5;
                        $gid  = 'verdict-half-' . ($post['id'] ?? 'x');
                    @endphp
                    <div class="flex items-center gap-2">
                        <div class="flex items-center" aria-hidden="true">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $full)
                                    <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.4 4.32a1 1 0 0 0 .95.69h4.54c.97 0 1.37 1.24.59 1.81l-3.67 2.67a1 1 0 0 0-.36 1.12l1.4 4.31c.3.92-.75 1.69-1.54 1.12l-3.67-2.66a1 1 0 0 0-1.18 0l-3.67 2.66c-.79.57-1.84-.2-1.54-1.12l1.4-4.31a1 1 0 0 0-.36-1.12L1.06 9.75c-.78-.57-.38-1.81.59-1.81h4.54a1 1 0 0 0 .95-.69l1.4-4.32z"/></svg>
                                @elseif($half && $i === $full + 1)
                                    <svg class="w-5 h-5" viewBox="0 0 20 20"><defs><linearGradient id="{{ $gid }}"><stop offset="50%" stop-color="#fbbf24"/><stop offset="50%" stop-color="#e5e7eb"/></linearGradient></defs><path fill="url(#{{ $gid }})" d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.4 4.32a1 1 0 0 0 .95.69h4.54c.97 0 1.37 1.24.59 1.81l-3.67 2.67a1 1 0 0 0-.36 1.12l1.4 4.31c.3.92-.75 1.69-1.54 1.12l-3.67-2.66a1 1 0 0 0-1.18 0l-3.67 2.66c-.79.57-1.84-.2-1.54-1.12l1.4-4.31a1 1 0 0 0-.36-1.12L1.06 9.75c-.78-.57-.38-1.81.59-1.81h4.54a1 1 0 0 0 .95-.69l1.4-4.32z"/></svg>
                                @else
                                    <svg class="w-5 h-5 text-gray-200" fill="currentColor" viewBox="0 0 20 20"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.4 4.32a1 1 0 0 0 .95.69h4.54c.97 0 1.37 1.24.59 1.81l-3.67 2.67a1 1 0 0 0-.36 1.12l1.4 4.31c.3.92-.75 1.69-1.54 1.12l-3.67-2.66a1 1 0 0 0-1.18 0l-3.67 2.66c-.79.57-1.84-.2-1.54-1.12l1.4-4.31a1 1 0 0 0-.36-1.12L1.06 9.75c-.78-.57-.38-1.81.59-1.81h4.54a1 1 0 0 0 .95-.69l1.4-4.32z"/></svg>
                                @endif
                            @endfor
                        </div>
                        <span class="text-sm font-bold text-gray-900">{{ rtrim(rtrim(number_format($rating, 1), '0'), '.') }}<span class="text-gray-400 font-medium"> / 5</span></span>
                    </div>
                @endif
            </div>

            @if(count($pros) > 0 || count($cons) > 0)
                <div class="grid sm:grid-cols-2 gap-5">
                    @if(count($pros) > 0)
                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-emerald-700 mb-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Pros
                            </p>
                            <ul class="space-y-1.5">
                                @foreach($pros as $pro)
                                    <li class="flex gap-2 text-sm text-gray-600"><span class="text-emerald-500 font-bold mt-px">+</span><span>{{ $pro }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(count($cons) > 0)
                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-rose-700 mb-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                Cons
                            </p>
                            <ul class="space-y-1.5">
                                @foreach($cons as $con)
                                    <li class="flex gap-2 text-sm text-gray-600"><span class="text-rose-400 font-bold mt-px">&minus;</span><span>{{ $con }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endif
