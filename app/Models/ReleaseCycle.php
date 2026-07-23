<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * An editorial release cycle for a product LINE (iPhone, AirPods Pro, GoPro
 * HERO): the half of the buy-or-wait verdict that price history can't supply.
 *
 * Every row carries a `source_url` and a `verified_at` because the whole
 * feature rests on the dates being right: stale cycle data is the highest-stakes
 * honesty risk on the site. Staleness is never hidden. It renders on the page,
 * downgrades the verdict's confidence (see {@see \App\Support\BuyOrWait}), and
 * shows up in the {@see scopeStale()} scope that /gd-health reads.
 *
 * The seeder is the editing interface (v1): data changes go through code review
 * like everything else, and a quarterly re-verify bumps `verified_at`.
 *
 * @see docs/plans/06-buy-or-wait.md
 */
class ReleaseCycle extends Model
{
    /** A row unchecked for longer than this is stale: confidence drops and the page says so. */
    public const STALE_AFTER_MONTHS = 6;

    /** Cycle position at or above which a successor is close enough to matter. */
    public const LATE_CYCLE = 0.8;

    /** Cycle position below which the current model is genuinely fresh. */
    public const FRESH_CYCLE = 0.35;

    protected $fillable = [
        'name', 'slug', 'category_id', 'typical_month', 'cadence_months',
        'last_release_name', 'last_release_at', 'next_expected_note',
        'source_url', 'verified_at',
    ];

    /**
     * Pinned to the framework default rather than inherited from the connection
     * grammar. Setting a date-cast attribute calls getDateFormat(), which
     * otherwise resolves a DB connection, which would make BuyOrWait's pure unit
     * tests (which build unsaved cycles with no container) impossible. Same
     * string MySQL and sqlite already use, so nothing about storage changes.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $casts = [
        'category_id' => 'integer',
        'typical_month' => 'integer',
        'cadence_months' => 'integer',
        'last_release_at' => 'date',
        'verified_at' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Whole months since the current model reached buyers (fractional, so the position moves daily). */
    public function monthsSinceRelease(): float
    {
        if (! $this->last_release_at) {
            return 0.0;
        }

        return max(0.0, round($this->last_release_at->floatDiffInMonths(now()), 2));
    }

    /**
     * Where we are in the line's cycle: 0.0 = just launched, 1.0 = a refresh is
     * due now, >1.0 = overdue (GoPro skipping a year lands here). Never negative.
     */
    public function cyclePosition(): float
    {
        if (! $this->cadence_months) {
            return 0.0;
        }

        return round($this->monthsSinceRelease() / $this->cadence_months, 3);
    }

    /** The month a refresh would land on if the line keeps its cadence. Null without a release date. */
    public function nextExpectedAt(): ?Carbon
    {
        if (! $this->last_release_at || ! $this->cadence_months) {
            return null;
        }

        return $this->last_release_at->copy()->addMonths($this->cadence_months);
    }

    /** Has a human re-checked this row against its source recently enough to trust it? */
    public function isStale(): bool
    {
        return ! $this->verified_at
            || $this->verified_at->lt(now()->subMonths(self::STALE_AFTER_MONTHS));
    }

    /**
     * Rows overdue for a re-verify; /gd-health reads this. The OR is grouped so
     * chaining another where() onto the scope can't smuggle stale rows back in.
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('verified_at')
            ->orWhere('verified_at', '<', now()->subMonths(self::STALE_AFTER_MONTHS)->toDateString()));
    }

    /**
     * Does this cycle describe the given product? Whole-word containment of the
     * line name in "{brand} {name}", so "AirPods Pro" matches "Apple AirPods Pro
     * 3", "iPhone" matches "iPhone 17 Pro Max".
     *
     * Known limitation: a sub-line inherits its parent's match ("iPad Air" hits
     * the "iPad" cycle). That is why every verdict sentence names the LINE it is
     * talking about rather than the reader's exact model, and why the fix for a
     * sub-line that diverges is to add its own row, not to widen this matcher.
     */
    public function matches(Product $product): bool
    {
        $haystack = self::normalize($product->brand.' '.$product->name);
        $needle = self::normalize($this->name);

        if ($needle === '') {
            return false;
        }

        // Whole-word, with one concession: a model number may run straight into
        // the line name ("GoPro HERO" → "gopro hero13 black").
        return (bool) preg_match(
            '/(^|\s)'.preg_quote($needle, '/').'(\d|\s|$)/',
            $haystack,
        );
    }

    /**
     * The cycle that describes a product, or null.
     *
     * 1. Name match, longest line name first (so "AirPods Pro" beats "AirPods").
     * 2. Category fallback ONLY when exactly one cycle maps to that category:
     *    three lines share the "computers" hub, and showing a Kindle reader the
     *    MacBook Air's refresh clock would be exactly the wrong advice.
     */
    public static function forProduct(Product $product): ?self
    {
        $cycles = self::query()->get();

        $named = $cycles
            ->filter(fn (self $cycle) => $cycle->matches($product))
            ->sortByDesc(fn (self $cycle) => mb_strlen($cycle->name))
            ->first();

        if ($named) {
            return $named;
        }

        if (! $product->category_id) {
            return null;
        }

        $inCategory = $cycles->where('category_id', $product->category_id);

        return $inCategory->count() === 1 ? $inCategory->first() : null;
    }

    /**
     * The tracked product this line's price context should come from: a product
     * with a published review that this cycle matches by name, newest review
     * first. Category alone is never enough here (same reasoning as
     * forProduct()), so a line with no name-matching review simply has no price
     * block, which is an honest omission.
     */
    public function flagshipProduct(): ?Product
    {
        $candidates = Product::query()
            ->whereHas('posts', fn ($q) => $q->published()->whereNotIn('type', ['tech_tip', 'tech_news']))
            ->whereNotNull('price')
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $candidates->first(fn (Product $product) => $this->matches($product));
    }

    /** Every cycle, ordered for display: soonest refresh first. */
    public static function ordered(): Collection
    {
        return self::query()->get()->sortByDesc(fn (self $c) => $c->cyclePosition())->values();
    }

    /** Lowercased, punctuation collapsed to single spaces: "GoPro HERO13" → "gopro hero13". */
    private static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value))));
    }
}
