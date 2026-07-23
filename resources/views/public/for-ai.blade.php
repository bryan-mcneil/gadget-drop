@extends('layouts.public')

@section('content')
{{-- Hero --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-3xl mx-auto px-4 py-16 text-center">
        <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest mb-3">For AI Agents</p>
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight mb-4">
            The Price-Truth MCP Server
        </h1>
        <p class="text-lg text-gray-500 leading-relaxed max-w-xl mx-auto">
            Give your AI assistant our independently tracked price history and honest deal verdicts —
            free, read-only, no account required.
        </p>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 py-14 space-y-14">
    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">What this is</h2>
        <p class="text-gray-600 leading-relaxed">
            GadgetDrop records the real price of every product we review, over time, and refuses to call
            anything a deal unless our own history backs it up. This server exposes that dataset over the
            <a href="https://modelcontextprotocol.io" rel="noopener" target="_blank" class="text-indigo-600 underline hover:text-indigo-700">Model Context Protocol</a>
            so an AI assistant can answer "is this actually a good deal?" with tracked numbers instead of
            marketing copy. It is read-only and serves only what is already public on this site.
        </p>
        <div class="bg-gray-50 rounded-lg p-4 overflow-x-auto">
            <p class="text-sm text-gray-500 mb-1">Endpoint (Streamable HTTP)</p>
            <code class="text-sm font-mono text-gray-900">{{ url('/mcp') }}</code>
        </div>
        <p class="text-gray-600 leading-relaxed">
            Two things every response makes explicit, and your assistant should too: the numbers are
            <strong>our tracked history, not live Amazon prices</strong> (each reply carries a
            <code class="text-sm font-mono">checked_at</code> timestamp), and stats stay
            <strong>null until a product has enough history</strong> — at least
            {{ \App\Support\PriceIntel::MIN_POINTS }} snapshots spanning
            {{ \App\Support\PriceIntel::MIN_SPAN_DAYS }} days. The restraint is the product. Full details in our
            {{-- no wire:navigate: fragment links need native navigation to land on the anchor --}}
            <a href="{{ route('how-we-review') }}#deal-verdicts" class="text-indigo-600 underline hover:text-indigo-700">deal-verdict methodology</a>,
            which the server also ships as a readable <code class="text-sm font-mono">methodology</code> resource.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">The tools</h2>
        <div class="space-y-3 text-sm">
            <div class="flex gap-3 items-baseline">
                <code class="shrink-0 font-mono font-bold text-gray-900">search_tracked_products</code>
                <span class="text-gray-600">What do we cover? Name/brand substring or exact ASIN → up to 10 matches with review URLs.</span>
            </div>
            <div class="flex gap-3 items-baseline">
                <code class="shrink-0 font-mono font-bold text-gray-900">get_price_history</code>
                <span class="text-gray-600">Full tracked history for one product: current price, 30/90-day low/avg/high, dated series, verdict.</span>
            </div>
            <div class="flex gap-3 items-baseline">
                <code class="shrink-0 font-mono font-bold text-gray-900">get_deal_verdict</code>
                <span class="text-gray-600">One honest, dated sentence — "18% below its tracked 90-day average" — plus the stats behind it.</span>
            </div>
            <div class="flex gap-3 items-baseline">
                <code class="shrink-0 font-mono font-bold text-gray-900">list_tracked_deals</code>
                <span class="text-gray-600">The live feed behind our <a href="{{ route('deals') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700">Price Drops page</a>: products ≥{{ \App\Support\DealsFeed::MIN_DROP_PCT }}% below their tracked 90-day average.</span>
            </div>
            <div class="flex gap-3 items-baseline">
                <code class="shrink-0 font-mono font-bold text-gray-900">ping</code>
                <span class="text-gray-600">Connectivity check: server time + tracked product count.</span>
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">Connect your assistant</h2>

        <div class="space-y-2">
            <h3 class="font-bold text-gray-900">Claude Code</h3>
            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                <code class="text-sm font-mono text-gray-100 whitespace-pre">claude mcp add --transport http gadgetdrop {{ url('/mcp') }}</code>
            </div>
        </div>

        <div class="space-y-2">
            <h3 class="font-bold text-gray-900">Claude Desktop / claude.ai</h3>
            <p class="text-gray-600 leading-relaxed">
                Settings → Connectors → <em>Add custom connector</em>, then paste
                <code class="text-sm font-mono">{{ url('/mcp') }}</code> as the remote MCP server URL.
            </p>
        </div>

        <div class="space-y-2">
            <h3 class="font-bold text-gray-900">Other MCP clients (JSON config)</h3>
            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
<code class="text-sm font-mono text-gray-100 whitespace-pre">{
  "mcpServers": {
    "gadgetdrop": {
      "type": "http",
      "url": "{{ url('/mcp') }}"
    }
  }
}</code>
            </div>
            <p class="text-gray-600 leading-relaxed text-sm">
                Any client that speaks MCP over Streamable HTTP works — ChatGPT developer-mode connectors,
                Cursor, and friends. No authentication, no API key.
            </p>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">Rate limits &amp; fair use</h2>
        <p class="text-gray-600 leading-relaxed">
            The server is free and anonymous, throttled at <strong>30 requests/minute</strong> and
            <strong>300 requests/day</strong> per IP, with tool inputs capped at 1&nbsp;KB. Every read is
            cache-backed, so a tool call costs about what a page view does — but if load ever strains the
            site, these limits may tighten (we'd document it here). Responses are marked
            <code class="text-sm font-mono">Cache-Control: no-store</code>; don't re-serve them as live prices.
        </p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-bold text-gray-900">Affiliate disclosure</h2>
        <p class="text-gray-600 leading-relaxed">
            GadgetDrop earns a commission on Amazon purchases made through the links this server returns —
            every response says so in a <code class="text-sm font-mono">disclosure</code> field, and an
            assistant relaying our links should relay that sentence with them. Product links route through
            our redirect (never raw Amazon URLs) so the relationship stays visible. That commission is the
            business model; the tracked history is why you can trust the verdict anyway — see
            <a href="{{ route('how-we-review') }}" wire:navigate class="text-indigo-600 underline hover:text-indigo-700">how we review</a>.
        </p>
    </section>
</div>
@endsection
