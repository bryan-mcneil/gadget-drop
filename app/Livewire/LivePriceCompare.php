<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\PriceCompareService;
use App\Support\PriceComparison;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Compare prices at other retailers" — an on-demand live lookup of what the
 * reviewed product costs elsewhere, rendered beside our own tracked Amazon
 * price.
 *
 * Nothing but the product id crosses the wire, and it is {@see Locked}: the
 * price, the retailer list, and every claim are re-derived server-side, so a
 * tampered payload cannot swap the product or inject a price. Same principle
 * as {@see WorthItVote} and {@see ExplainVerdict}.
 *
 * Note what is cached and what is not: the RAW model payload goes in the
 * cache, and {@see PriceComparison::normalize()} runs on every read. That is
 * deliberate. The freshness gate on the winner claim depends on
 * `price_checked_at`, so recomputing at read time means an admin re-checking
 * the price brings the verdict line back without spending another API call,
 * while a changed PRICE lands on a different cache key and correctly triggers
 * a fresh search.
 *
 * @see docs/plans/11-live-price-compare.md Phase 11.3
 */
class LivePriceCompare extends Component
{
    /** Locked so the product being priced cannot be swapped by a tampered payload. */
    #[Locked]
    public int $productId;

    /** idle | ready | empty | unavailable | gated | throttled | failed */
    public string $phase = 'idle';

    /**
     * The presentable result. Scalars only: Livewire has to serialise this
     * between requests, so dates are formatted here rather than carried as
     * Carbon instances.
     *
     * @var array<string, mixed>
     */
    public array $result = [];

    public function mount(int $productId): void
    {
        $this->productId = $productId;

        // Decided at mount, so a disabled or unpriced product never renders a
        // button the reader would only get an apology from.
        if (! $this->product() || ! $this->isAvailable()) {
            $this->phase = 'gated';
        }
    }

    public function compare(): void
    {
        $product = $this->product();

        if (! $product || ! $this->isAvailable()) {
            $this->phase = 'gated';

            return;
        }

        $key = $this->cacheKey($product);
        $cached = Cache::get($key);

        // A cache hit costs nothing, so it is deliberately NOT rate limited.
        if ($cached !== null) {
            $this->apply($cached, $product);

            return;
        }

        $rateLimitKey = 'price-compare:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->phase = 'throttled';

            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        try {
            $raw = Cache::lock("price-compare-lock.{$key}", 30)->block(8, function () use ($key, $product) {
                // Re-check inside the lock: another request may have just paid
                // for this exact lookup while we waited.
                $cached = Cache::get($key);

                if ($cached !== null) {
                    return $cached;
                }

                $raw = app(PriceCompareService::class)->compare($product);

                // A failure is never cached, so an immediate retry is free to
                // succeed. Every real answer is cached, including "found
                // nothing" and a low-confidence one: re-asking would spend
                // another call to be told the same thing.
                if ($raw !== null) {
                    Cache::put($key, $raw, now()->addHours((int) config('price-compare.cache_hours', 24)));
                }

                return $raw;
            });
        } catch (LockTimeoutException) {
            // Someone else is mid-lookup. One more look: it likely just landed.
            $raw = Cache::get($key);
        }

        if ($raw === null) {
            $this->phase = 'failed';

            return;
        }

        $this->apply($raw, $product);
    }

    /**
     * Turn a raw payload into what the view renders, and pick the phase.
     *
     * @param  array<string, mixed>  $raw
     */
    private function apply(array $raw, Product $product): void
    {
        $normalized = PriceComparison::normalize(
            $raw,
            $product->price !== null ? (float) $product->price : null,
            $product->price_checked_at,
            (array) config('price-compare.retailers', []),
            (int) config('price-compare.fresh_days', 7),
            (float) config('price-compare.confidence_floor', 0.6),
        );

        if ($normalized['suppressed']) {
            $this->phase = 'unavailable';
            $this->result = ['retailers_checked' => $normalized['retailers_checked']];

            return;
        }

        $this->result = [
            'rows' => $normalized['rows'],
            'retailers_checked' => $normalized['retailers_checked'],
            'our_price' => $normalized['our_price'],
            'our_checked_at' => $normalized['our_checked_at']?->format('M j, Y'),
            'is_fresh' => $normalized['is_fresh'],
            'winner' => $normalized['winner'],
            'winner_label' => $normalized['winner_label'],
            'checked_on' => Carbon::now()->format('M j, Y'),
        ];

        $this->phase = $normalized['is_empty'] ? 'empty' : 'ready';
    }

    /**
     * The cache key is fingerprinted with our own price ONLY, not with
     * price_checked_at: a changed price invalidates the comparison and must
     * buy a fresh search, but a re-check that confirms the same price should
     * not. Freshness is recomputed from the product on every read instead.
     */
    private function cacheKey(Product $product): string
    {
        return sprintf(
            'price-compare.v1.%d.%s',
            $product->id,
            md5((string) $product->price),
        );
    }

    private function isAvailable(): bool
    {
        $service = app(PriceCompareService::class);

        return $service->isEnabled()
            && $service->isConfigured()
            && $this->product()?->price !== null;
    }

    private function product(): ?Product
    {
        return Product::find($this->productId);
    }

    public function render()
    {
        return view('livewire.live-price-compare', [
            'retailerCount' => count((array) config('price-compare.retailers', [])),
        ]);
    }
}
