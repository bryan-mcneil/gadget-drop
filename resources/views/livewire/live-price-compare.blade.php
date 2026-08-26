{{-- Live retailer price comparison.

     Deliberately NOT a CTA: the product card above holds the single affiliate
     link, and the retailer names/prices here are plain text with no anchors
     (the only links on this widget are the nofollow sources disclosure). Never
     add an amazon.com link in here.

     Nothing in this widget is server-rendered with data: it only fills in
     after a click, so crawlers see the button and nothing else. --}}

<div>
    @if($phase === 'gated')
        {{-- Feature off, unconfigured, or no tracked price to compare
             against. Render nothing at all rather than a dead button. --}}
    @else
        <div class="border border-gray-200 rounded-xl px-5 py-4 bg-white">
            <div class="flex items-center gap-2 text-xs font-bold text-gray-500 uppercase tracking-wider">
                <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                Other retailers
            </div>

            @if(in_array($phase, ['idle', 'throttled', 'failed', 'exhausted'], true))
                <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                    Check what this costs at {{ $retailerCount }} other major US retailers right now.
                </p>

                <button type="button" wire:click="compare" wire:loading.attr="disabled" wire:target="compare"
                    class="mt-3 inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:border-gray-400 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg wire:loading wire:target="compare" class="h-4 w-4 shrink-0 animate-spin motion-reduce:animate-none text-gray-500" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="compare">Compare prices at other retailers</span>
                    <span wire:loading wire:target="compare">Checking {{ $retailerCount }} retailers&hellip;</span>
                </button>

                <p class="mt-2 text-xs text-gray-500">
                    Runs a live search of retailer sites. Takes a few seconds.
                </p>

                @if($phase === 'failed')
                    <p class="mt-2 text-xs text-gray-500">That didn't come back in time. Try again in a moment.</p>
                @endif

                @if($phase === 'throttled')
                    <p class="mt-2 text-xs text-gray-500">You've run a few of these already. Try again a little later.</p>
                @endif

                @if($phase === 'exhausted')
                    {{-- Deliberately not "try again in a moment": the daily
                         budget does not come back in a moment. --}}
                    <p class="mt-2 text-xs text-gray-500">We can't run a live check right now. Try again tomorrow.</p>
                @endif
            @endif

            @if($phase === 'unavailable')
                {{-- The model could not confidently tell this exact model apart
                     from its variants. Saying so is more useful than a table
                     that might be priced on the wrong capacity or generation. --}}
                <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                    We checked other retailers but couldn't confidently match this exact model, so we're not going to show you prices that might be for a different version of it.
                </p>
            @endif

            @if($phase === 'empty')
                <p class="mt-2 text-sm text-gray-700 leading-relaxed">
                    We searched the major US retailers we cover and couldn't find this at any of them. This one looks Amazon-exclusive.
                </p>
            @endif

            @if($phase === 'ready')
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Current prices at other retailers, compared with our tracked Amazon price</caption>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($result['rows'] as $row)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-700">
                                        {{ $row['retailer'] }}
                                        @if(! $row['in_stock'])
                                            <span class="ml-1.5 text-xs text-gray-500">out of stock</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right font-semibold text-gray-900 tabular-nums whitespace-nowrap">
                                        ${{ number_format($row['price'], 2) }}
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Our own tracked figure, never a price scraped
                                 from Amazon (Associates 2(b)). Dated, because a
                                 price refreshed less than hourly must carry a
                                 stamp next to it. --}}
                            <tr class="bg-gray-50/60">
                                <td class="py-2 pr-4 text-gray-700">
                                    Our tracked Amazon price
                                    @if($result['our_checked_at'])
                                        <span class="block text-xs text-gray-500">checked {{ $result['our_checked_at'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right font-semibold text-gray-900 tabular-nums whitespace-nowrap align-top">
                                    ${{ number_format($result['our_price'], 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if($result['winner_label'])
                    <p class="mt-3 text-sm font-semibold text-gray-900 leading-relaxed">{{ $result['winner_label'] }}</p>
                @elseif(! $result['is_fresh'])
                    {{-- The bias guard, said out loud. Competitor prices came
                         back live; ours is a last-known figure, so we show both
                         and decline to name a winner. --}}
                    <p class="mt-3 text-sm text-gray-600 leading-relaxed">
                        The retailer prices above are live, but our own tracked price is a last-known figure from {{ $result['our_checked_at'] ?? 'an earlier check' }}, so we're not going to call a winner from it.
                    </p>
                @endif
            @endif

            @if(in_array($phase, ['ready', 'empty', 'unavailable'], true))
                <p class="mt-3 text-xs text-gray-500 leading-relaxed">
                    Checked {{ $result['checked_on'] ?? now()->format('M j, Y') }}. Prices found by an AI search of retailer sites and may be out of date. Always confirm at checkout.
                </p>

                @if($phase === 'ready' && count($result['rows']) > 0)
                    <details class="mt-2 group">
                        <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700">
                            Sources ({{ count($result['rows']) }})
                        </summary>
                        <ul class="mt-2 space-y-1">
                            @foreach($result['rows'] as $row)
                                <li>
                                    <a href="{{ $row['url'] }}" target="_blank" rel="nofollow noopener noreferrer"
                                        class="text-xs text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-gray-700 break-all">
                                        {{ $row['retailer'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif
        </div>
    @endif
</div>
