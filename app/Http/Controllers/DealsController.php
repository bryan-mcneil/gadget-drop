<?php

namespace App\Http\Controllers;

use App\Support\DealsFeed;
use App\Support\TruthReport;
use Illuminate\Contracts\View\View;

/**
 * "Price Drops" feed — products whose CURRENT price sits meaningfully below
 * what our own snapshot history says is typical. Every number on the page
 * comes from tracked data (never an MSRP), and products qualify only once
 * PriceIntel's honesty gates pass, so an empty feed is a truthful feed.
 *
 * The feed itself is built by App\Support\DealsFeed (shared with the MCP
 * list_tracked_deals tool); this controller only renders it.
 */
class DealsController extends Controller
{
    /** @deprecated The feed rules live in DealsFeed; these aliases keep existing view/test references valid. */
    public const MIN_DROP_PCT = DealsFeed::MIN_DROP_PCT;

    /** @deprecated See MIN_DROP_PCT. */
    public const MAX_ENTRIES = DealsFeed::MAX_ENTRIES;

    public function index(): View
    {
        $deals = DealsFeed::get();

        view()->share('serverMeta', [
            'title' => 'Tech Price Drops We Actually Tracked | GadgetDrop',
            'description' => 'Real price drops on gadgets we cover — measured against our own recorded price history, not inflated list prices. Updated as our tracker sees changes.',
            'og_image' => null,
            'og_type' => 'website',
            'canonical' => route('deals'),
        ]);

        // Event-week pointer to a Truth Report: config names the slug AND the
        // report must pass the publication gate, or the line stays hidden.
        $promoSlug = config('truth.promote_on_deals');
        $published = TruthReport::published();

        return view('public.deals', [
            'deals' => $deals,
            // Honest trust chips (each hidden when its number is zero): how many
            // products we track prices for, drops live now, and the biggest one.
            'trackedCount' => DealsFeed::trackedCount(),
            'truthPromo' => ($promoSlug && isset($published[$promoSlug]))
                ? ['slug' => $promoSlug, 'title' => $published[$promoSlug]['title']]
                : null,
        ]);
    }
}
