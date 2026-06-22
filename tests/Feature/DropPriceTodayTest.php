<?php

namespace Tests\Feature;

use App\Models\DropPricePuzzle;
use App\Support\DropPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature coverage for DropPrice::today() — the cached resolution of which puzzle
 * the homepage serves right now. The pure Unit\DropPriceTest only exercises
 * evaluate(); this hits the DB + cache path that the unit test deliberately can't.
 */
class DropPriceTodayTest extends TestCase
{
    use RefreshDatabase;

    /** A locked puzzle row. Snapshots stand in for a product, so no Product/Category needed. */
    private function puzzle(array $overrides = []): DropPricePuzzle
    {
        return DropPricePuzzle::create(array_merge([
            'puzzle_number'        => (int) (DropPricePuzzle::max('puzzle_number') ?? 0) + 1,
            'date'                 => now()->toDateString(),
            'product_id'           => null,
            'price'                => 100,
            'product_name'         => 'Snapshot Widget',
            'product_image_url'    => 'https://example.com/img.jpg',
            'affiliate_product_id' => null,
            'locked_at'            => now(),
        ], $overrides));
    }

    public function test_today_resolves_the_latest_locked_puzzle_dated_today(): void
    {
        $this->puzzle(['date' => now()->subDays(3)->toDateString()]);
        $today = $this->puzzle(['date' => now()->toDateString()]);

        $this->assertSame($today->id, DropPrice::today()?->id);
    }

    public function test_today_falls_back_to_the_most_recent_prior_puzzle_before_todays_is_locked(): void
    {
        // No puzzle for today yet (the hourly cron hasn't locked it). The homepage
        // must still show the most recent prior puzzle rather than going blank.
        $this->puzzle(['date' => now()->subDays(5)->toDateString()]);
        $yesterday = $this->puzzle(['date' => now()->subDay()->toDateString()]);

        $this->assertSame($yesterday->id, DropPrice::today()?->id);
    }

    public function test_today_excludes_an_unlocked_future_preset(): void
    {
        $today = $this->puzzle(['date' => now()->toDateString()]);

        // A queued preset for a future day (still unlocked → no number, no locked_at).
        $this->puzzle([
            'puzzle_number' => null,
            'date'          => now()->addDays(2)->toDateString(),
            'locked_at'     => null,
            'is_preset'     => true,
        ]);

        $this->assertSame($today->id, DropPrice::today()?->id);
    }

    public function test_today_never_serves_a_future_dated_puzzle(): void
    {
        // Defensive: even a *locked* row dated in the future must not be served today.
        $this->puzzle(['date' => now()->addDay()->toDateString(), 'locked_at' => now()]);

        $this->assertNull(DropPrice::today());
    }

    public function test_today_is_null_when_nothing_is_locked(): void
    {
        // An unlocked preset dated today is not yet a live puzzle.
        $this->puzzle([
            'puzzle_number' => null,
            'date'          => now()->toDateString(),
            'locked_at'     => null,
            'is_preset'     => true,
        ]);

        $this->assertNull(DropPrice::today());
    }

    public function test_today_is_cached_and_forget_re_resolves(): void
    {
        // Only a prior puzzle exists → today() resolves (and caches) the fallback.
        $yesterday = $this->puzzle(['date' => now()->subDay()->toDateString()]);
        $this->assertSame($yesterday->id, DropPrice::today()?->id);
        $this->assertTrue(Cache::has('dropprice.today'));

        // The cron locks today's puzzle. Until the cache is busted, today() is stale.
        $todays = $this->puzzle(['date' => now()->toDateString()]);
        $this->assertSame($yesterday->id, DropPrice::today()?->id);

        // The lock command calls Cache::forget('dropprice.today'); now it re-resolves.
        Cache::forget('dropprice.today');
        $this->assertSame($todays->id, DropPrice::today()?->id);
    }
}
