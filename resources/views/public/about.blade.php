@extends('layouts.public')

@section('content')
{{-- Hero --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-3xl mx-auto px-4 py-16 text-center">
        <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-3">About</p>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
            Gadget<span class="text-indigo-600">Drop</span>
        </h1>
        <p class="text-lg text-gray-500 leading-relaxed max-w-xl mx-auto">
            A daily tech picks site built for people who want the honest story on gear, not a wall of specs and marketing fluff.
        </p>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 py-14 space-y-14">
    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">What we do</h2>
        <p class="text-gray-600 leading-relaxed">
            GadgetDrop publishes daily reviews, buying guides, and tech tips focused on consumer electronics and gadgets available on Amazon.
            Every post is written to answer one question: <em>is this worth your money?</em> We skip the spec sheets and focus on real-world use: who it's for, what it actually does well, and when you should skip it.
        </p>
        <p class="text-gray-600 leading-relaxed">
            We also publish Tech Tips: short, actionable guides to common tech problems drawn from real community discussions. No filler, no padding: just the fix.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">Who's behind GadgetDrop</h2>
        <p class="text-gray-600 leading-relaxed">
            GadgetDrop is written and edited by {{ config('site.author.name') }}.
            {{-- TODO (personalise — keep it true): replace the next sentence with your own
                     background: how long you've followed consumer tech, what you actually use,
                     and why you started the site. --}}
            I'm a lifelong tech enthusiast who got tired of "reviews" that just reword the spec sheet,
            so I started GadgetDrop to write the plain-English buying advice I wished existed:
            who a gadget is really for, where it falls short, and when you're better off keeping your money.
        </p>
        <p class="text-gray-600 leading-relaxed">
            Everything on this site is published under my name and I stand behind all of it. You can browse every
            article I've written on
            <a href="{{ route('author', config('site.author.slug')) }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700">my author page</a>.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">How we choose and evaluate products</h2>
        <p class="text-gray-600 leading-relaxed">
            Picks are based on hands-on use where I have it, plus manufacturer specifications, verified-purchase owner
            reviews, professional reviews, and wider community discussion. When a verdict leans on research rather than
            long-term personal testing, I say so in the article instead of implying experience I don't have.
        </p>
        <p class="text-gray-600 leading-relaxed">
            I use AI tools to help with drafting and research, but every post is personally reviewed, fact-checked, and
            edited before it goes live. AI is a writing aid here — never a substitute for a real person taking
            responsibility for what gets published.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">Corrections &amp; accuracy</h2>
        <p class="text-gray-600 leading-relaxed">
            Tech moves fast, and specs and prices change. If you spot something wrong or out of date, email
            <a href="mailto:{{ config('site.author.email') }}" class="text-indigo-600 underline hover:text-indigo-700">{{ config('site.author.email') }}</a>
            and I'll look into it. Substantive corrections are updated directly in the article, and the price you see is
            always confirmed at the source before you buy.
        </p>
    </section>

    <section class="space-y-4 bg-amber-50 border border-amber-100 rounded-xl p-6">
        <h2 class="text-lg font-bold text-gray-900">Affiliate disclosure</h2>
        <p class="text-gray-600 leading-relaxed text-sm">
            GadgetDrop is a participant in the Amazon Services LLC Associates Program, an affiliate advertising program designed to provide a means for sites to earn advertising fees by advertising and linking to Amazon.com.
            When you click an Amazon link on GadgetDrop and make a qualifying purchase, we may earn a small commission at <strong>no extra cost to you</strong>.
            This never influences our editorial recommendations: we only cover products we genuinely believe are worth your attention.
        </p>
    </section>

    <section class="space-y-3 text-center">
        <h2 class="text-xl font-bold text-gray-900">Get in touch</h2>
        <p class="text-gray-500">Questions, corrections, or partnership enquiries? We'd love to hear from you.</p>
        <a href="{{ route('contact') }}" wire:navigate
            class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
            Contact us →
        </a>
    </section>
</div>
@endsection