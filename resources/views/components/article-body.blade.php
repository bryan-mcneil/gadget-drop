@props([
    'sections' => [],  // array of ['html' => ..., 'image' => ..., 'fit' => ..., 'caption' => ...]
])

<div class="post-body">
    @foreach($sections as $section)
        <div>
            @if(!empty($section['html']))
                <div class="prose prose-gray max-w-none">
                    {!! $section['html'] !!}
                </div>
            @endif
            @if(!empty($section['image']))
                {{-- Framed figure, not a floating scrap: contain-fit images (pack
                     shots, marketing graphics) sit on a tinted mat; cover-fit
                     images span the column. The visible <figcaption> is the
                     accessible name, so alt stays empty (no double announce). --}}
                <figure class="not-prose my-10">
                    @if(($section['fit'] ?? 'cover') === 'contain')
                        <div class="rounded-2xl border border-gray-200/80 bg-gradient-to-br from-slate-50 to-indigo-50/40 p-6 sm:p-8 flex justify-center">
                            <x-adaptive-image
                                :src="$section['image']"
                                alt=""
                                fit="contain"
                                class="max-h-96 w-auto max-w-full rounded-lg shadow-sm" />
                        </div>
                    @else
                        <x-adaptive-image
                            :src="$section['image']"
                            alt=""
                            fit="cover"
                            class="w-full rounded-2xl border border-gray-200/80 max-h-[28rem]" />
                    @endif
                    @if(!empty($section['caption']))
                        <figcaption class="mt-3 text-center text-sm text-gray-500">{{ $section['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
        </div>
    @endforeach
</div>
