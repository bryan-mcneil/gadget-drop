@extends('layouts.public')

@section('content')
{{-- Hero --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-3xl mx-auto px-4 py-16 text-center">
        <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-3">Methodology</p>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
            How We Review Products
        </h1>
        <p class="text-lg text-gray-500 leading-relaxed max-w-xl mx-auto">
            Exactly what a GadgetDrop review is based on, how ratings are assigned, and where our price data comes from. No pretending.
        </p>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 py-14 space-y-14">
    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">What our reviews are based on</h2>
        <p class="text-gray-600 leading-relaxed">
            GadgetDrop reviews are <strong>research-based</strong>. We don't run a testing lab, and we don't
            pretend to. Unless an article explicitly says otherwise, a review is built from four sources,
            weighed against each other:
        </p>
        <ul class="space-y-3 text-gray-600 leading-relaxed list-none">
            <li class="flex gap-3">
                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center mt-0.5">1</span>
                <span><strong>Manufacturer specifications:</strong> the claimed numbers, read critically. A spec sheet tells you what a product promises, not what it delivers, so we treat it as the starting point.</span>
            </li>
            <li class="flex gap-3">
                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center mt-0.5">2</span>
                <span><strong>Owner feedback in volume:</strong> hundreds of verified-purchase reviews, read for patterns rather than individual anecdotes. When owners consistently report the same strength or the same flaw, that pattern carries more weight than any single opinion, including ours.</span>
            </li>
            <li class="flex gap-3">
                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center mt-0.5">3</span>
                <span><strong>Professional reviews and teardowns:</strong> publications and channels that do physically test hardware. Where measured results exist, we defer to them and say where the claim comes from.</span>
            </li>
            <li class="flex gap-3">
                <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center mt-0.5">4</span>
                <span><strong>Our own price tracking:</strong> the one dataset that is genuinely ours. We record real Amazon prices over time and use that history to judge whether a product is actually worth buying <em>today</em> (more below).</span>
            </li>
        </ul>
        <p class="text-gray-600 leading-relaxed">
            You will never read "we tested" or "our measurements" on GadgetDrop unless someone here physically
            did the thing being described. When a verdict leans on research, the article says so.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">How ratings are assigned</h2>
        <p class="text-gray-600 leading-relaxed">
            Every review carries a rating out of 5, assigned by the editor after the research above, not
            copied from Amazon's star average. The scale is deliberately blunt:
        </p>
        <div class="space-y-2 text-sm">
            <div class="flex gap-3 items-baseline"><span class="font-bold text-gray-900 w-16 shrink-0">4.5–5</span><span class="text-gray-600">Best in its class at its price. We'd buy it ourselves without hesitation.</span></div>
            <div class="flex gap-3 items-baseline"><span class="font-bold text-gray-900 w-16 shrink-0">4–4.5</span><span class="text-gray-600">Genuinely good with a caveat you should know about before paying.</span></div>
            <div class="flex gap-3 items-baseline"><span class="font-bold text-gray-900 w-16 shrink-0">3–4</span><span class="text-gray-600">Fine for a specific person or price. The review spells out who.</span></div>
            <div class="flex gap-3 items-baseline"><span class="font-bold text-gray-900 w-16 shrink-0">Under 3</span><span class="text-gray-600">We'd skip it. If we can't recommend something to anyone, we usually don't cover it at all rather than farm clicks from a takedown.</span></div>
        </div>
        <p class="text-gray-600 leading-relaxed">
            Every review also lists explicit pros and cons in the verdict box, and every review names at least
            one real downside. A product page with no honest drawback is marketing, not a review.
        </p>
    </section>

    {{-- #deal-verdicts: the deal-verdict methodology the MCP server (Plan 04) and
         Phase 4.3's methodology resource cite. Keep this anchor stable. --}}
    <section id="deal-verdicts" class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">How our price data works</h2>
        <p class="text-gray-600 leading-relaxed">
            "Was $199, now $149" claims on the internet are usually built on inflated list prices. Ours aren't.
            We record the price of every product we cover each time we check it, and that recorded history,
            not the manufacturer's suggested price, is what our price commentary is based on.
        </p>
        <p class="text-gray-600 leading-relaxed">
            Where you see a price on GadgetDrop, you'll also see <strong>when we last checked it</strong>.
            Prices on Amazon change constantly, so always confirm the final price at checkout. The number
            there is the only one that counts. When we call something a good deal, it means the current price
            sits below what our own tracking says is typical for that product, and we show our working.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">How AI is used here</h2>
        <p class="text-gray-600 leading-relaxed">
            We use AI tools to help with research aggregation and drafting, the same way other publications
            use spellcheckers and research assistants, just more capable. What AI does not do here is publish.
            Every article is reviewed, fact-checked, and edited by {{ config('site.author.name') }} before it
            goes live, published under his name, and he is accountable for every claim in it. If something
            gets past that process and turns out wrong, we correct the article directly and say so.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">Affiliate links and independence</h2>
        <p class="text-gray-600 leading-relaxed">
            GadgetDrop earns a commission when you buy through our Amazon links, at no extra cost to you.
            That's the business model, disclosed on every page it applies to. It doesn't change the math on
            a rating: the commission on a product we'd tell you to skip is worth less than your trust, and
            the "when to skip it" section exists in our reviews for exactly that reason. We don't accept
            payment for coverage or ratings, and no brand sees an article before you do.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">Corrections</h2>
        <p class="text-gray-600 leading-relaxed">
            Specs change, prices move, and sometimes we're just wrong. If you spot an error, use the
            <a href="{{ route('contact') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700">contact form</a>
            or email <a href="mailto:{{ config('site.author.email') }}" class="text-indigo-600 underline hover:text-indigo-700">{{ config('site.author.email') }}</a>.
            Substantive fixes are made directly in the article. Being correctable is the whole point of
            publishing under a real name.
        </p>
    </section>

    <section class="space-y-3 text-center">
        <p class="text-gray-500">More about the site and the person behind it:</p>
        <a href="{{ route('about') }}" wire:navigate
            class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
            About GadgetDrop →
        </a>
    </section>
</div>
@endsection
