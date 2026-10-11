{{-- The deal-verdict methodology — the SINGLE copy of this content. Rendered as
     HTML inside /how-we-review (which passes $truthReports) and rendered-then-
     stripped to text by the MCP `methodology` resource (App\Mcp\Resources\
     DealVerdictMethodology), so the page and the agent-facing text can never
     fork. Keep the #deal-verdicts anchor stable: server instructions, tool
     responses, and /for-ai all deep-link to it. --}}
<section id="deal-verdicts" class="space-y-4 scroll-mt-24">
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

    @php
        // The copy quotes PriceIntel's real gates so these numbers can't
        // drift from the code (plan 01 §1.4 checklist). DEAL_PCT is a
        // fraction; render it as a percentage.
        $gateMinPoints = \App\Support\PriceIntel::MIN_POINTS;
        $gateMinSpanDays = \App\Support\PriceIntel::MIN_SPAN_DAYS;
        $gateDealPct = \App\Support\PriceIntel::DEAL_PCT * 100;
        $gateDealsBar = \App\Support\DealsFeed::MIN_DROP_PCT;
    @endphp

    <div class="space-y-2">
        <h3 class="font-bold text-gray-900">Where the numbers come from</h3>
        <p class="text-gray-600 leading-relaxed">
            We keep a snapshot log for every product we cover: one entry each time we check the
            price, from the editor's manual checks and from Amazon's product-data APIs. A snapshot
            is recorded when the price changes and when a check confirms it held, so the history
            shows how long a price actually lasted, not just when it happened to move. We never
            scrape Amazon pages.
        </p>
    </div>

    <div class="space-y-2">
        <h3 class="font-bold text-gray-900">The honesty gates</h3>
        <p class="text-gray-600 leading-relaxed">
            Averages, lows, and verdicts stay hidden until a product has at least
            {{ $gateMinPoints }} price snapshots spanning at least {{ $gateMinSpanDays }} days.
            Two checks recorded yesterday prove nothing; "held at one price for weeks, now lower"
            is a claim our history can actually back. Until the gate passes, we show the current
            price and when we checked it, nothing more.
        </p>
    </div>

    <div class="space-y-2">
        <h3 class="font-bold text-gray-900">The four verdicts</h3>
        <p class="text-gray-600 leading-relaxed">
            Once the gates pass, we compare today's price to that product's own tracked 90-day
            average, weighing each price by how long it held. These are the exact labels you'll
            see on reviews and deal cards:
        </p>
        <div class="space-y-3 text-sm">
            <div class="flex gap-3 items-baseline">
                <span class="shrink-0"><x-verdict-badge verdict="lowest" /></span>
                <span class="text-gray-600">The current price matches the lowest point in our 90-day history, and the price has genuinely varied in that window. A price that never moved can't earn this.</span>
            </div>
            <div class="flex gap-3 items-baseline">
                <span class="shrink-0"><x-verdict-badge verdict="good" /></span>
                <span class="text-gray-600">At least {{ $gateDealPct }}% below the 90-day average.</span>
            </div>
            <div class="flex gap-3 items-baseline">
                <span class="shrink-0"><x-verdict-badge verdict="typical" /></span>
                <span class="text-gray-600">Within {{ $gateDealPct }}% of the 90-day average. Most prices, most of the time.</span>
            </div>
            <div class="flex gap-3 items-baseline">
                <span class="shrink-0"><x-verdict-badge verdict="elevated" /></span>
                <span class="text-gray-600">At least {{ $gateDealPct }}% above the 90-day average. Usually worth waiting.</span>
            </div>
        </div>
        <p class="text-gray-600 leading-relaxed">
            Our <a href="{{ route('deals') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700">Price Drops page</a>
            holds the same line: a product is listed only when it carries one of the first two
            verdicts, its current price sits at least {{ $gateDealsBar }}% below its tracked
            90-day average, and we have a published review for it.
        </p>
    </div>

    <div class="space-y-2">
        <h3 class="font-bold text-gray-900">The 30-day reference price</h3>
        <p class="text-gray-600 leading-relaxed">
            Alongside the verdict we show the lowest price we've tracked in the last 30 days.
            EU rules require retailers to disclose that figure when they advertise a discount;
            US law has no equivalent, and we publish it voluntarily. A discount you can't check
            against last month isn't a discount, it's a claim.
        </p>
    </div>

    <div class="space-y-2">
        <h3 class="font-bold text-gray-900">What we never do</h3>
        <p class="text-gray-600 leading-relaxed">
            <strong>No MSRP theater.</strong> We never call something a deal because it sits below
            a manufacturer's suggested price nobody actually paid.
        </p>
        <p class="text-gray-600 leading-relaxed">
            <strong>No scraping.</strong> Our numbers come from our own checks and Amazon's
            product-data APIs, never from scraping Amazon pages.
        </p>
        <p class="text-gray-600 leading-relaxed">
            <strong>No manufactured urgency.</strong> No countdown timers, no invented stock
            warnings. If a price is good, the history says so on its own.
        </p>
    </div>

    @if(($truthReports ?? []) !== [])
        <p class="text-gray-600 leading-relaxed">
            After every major sale event we grade each tracked product's "deal" against its own
            pre-event price history and publish the full per-product data.
            <a href="{{ route('truth.index') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700 font-semibold">See our Truth Reports &rarr;</a>
        </p>
    @endif
</section>
