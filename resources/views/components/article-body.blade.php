@props([
    'sections' => [],  // array of ['html' => ..., 'image' => ..., 'fit' => ...]
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
                <x-adaptive-image
                    :src="$section['image']"
                    alt=""
                    :fit="$section['fit'] ?? 'cover'"
                    class="w-full rounded-xl max-h-80"
                    wrapper-class="my-8" />
            @endif
        </div>
    @endforeach
</div>
