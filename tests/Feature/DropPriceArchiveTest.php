<?php

namespace Tests\Feature;

use App\Models\DropPricePuzzle;
use App\Support\DropPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DropPriceArchiveTest extends TestCase
{
    use RefreshDatabase;

    /** A locked puzzle row. Snapshots stand in for a product, so no Product/Category needed. */
    private function puzzle(array $overrides = []): DropPricePuzzle
    {
        return DropPricePuzzle::create(array_merge([
            'puzzle_number'        => (int) (DropPricePuzzle::max('puzzle_number') ?? 0) + 1,
            'date'                 => now()->toDateString(),
            'product_id'           => null,
            'price'                => 613,
            'product_name'         => 'Snapshot Widget',
            'product_image_url'    => 'https://example.com/img.jpg',
            'affiliate_product_id' => null,
            'locked_at'            => now(),
        ], $overrides));
    }

    // ── Index ──────────────────────────────────────────────────────

    public function test_index_lists_locked_puzzles_newest_first(): void
    {
        // A decoy dated today so none of the three puzzles below is mistaken for
        // the currently-active one via the cron-gap fallback.
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Decoy Active Today']);
        $this->puzzle(['date' => now()->subDays(3)->toDateString(), 'product_name' => 'Oldest Gadget']);
        $this->puzzle(['date' => now()->subDays(2)->toDateString(), 'product_name' => 'Middle Gadget']);
        $this->puzzle(['date' => now()->subDay()->toDateString(), 'product_name' => 'Newest Gadget']);

        $html = $this->get('/drop-price')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Middle Gadget'), strpos($html, 'Newest Gadget'));
        $this->assertLessThan(strpos($html, 'Oldest Gadget'), strpos($html, 'Middle Gadget'));
    }

    public function test_index_excludes_the_currently_active_puzzle(): void
    {
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Active Today Gadget']);
        $this->puzzle(['date' => now()->subDay()->toDateString(), 'product_name' => 'Yesterday Gadget']);

        $this->get('/drop-price')
            ->assertOk()
            ->assertDontSee('Active Today Gadget')
            ->assertSee('Yesterday Gadget');
    }

    public function test_index_excludes_an_unlocked_future_preset(): void
    {
        // A decoy dated today so 'Locked Gadget' (yesterday) isn't itself mistaken
        // for the currently-active puzzle via the cron-gap fallback.
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Decoy Active Today']);
        $this->puzzle(['date' => now()->subDay()->toDateString(), 'product_name' => 'Locked Gadget']);
        $this->puzzle([
            'puzzle_number' => null,
            'date'          => now()->addDays(2)->toDateString(),
            'locked_at'     => null,
            'is_preset'     => true,
            'product_name'  => 'Queued Future Gadget',
        ]);

        $this->get('/drop-price')
            ->assertOk()
            ->assertSee('Locked Gadget')
            ->assertDontSee('Queued Future Gadget');
    }

    public function test_index_handles_the_cron_gap_correctly(): void
    {
        // No puzzle dated today — DropPrice::today() falls back to yesterday's,
        // which is therefore the currently-ACTIVE puzzle and must not also show
        // up in the archive.
        $this->puzzle(['date' => now()->subDays(5)->toDateString(), 'product_name' => 'Stale Gadget']);
        $activeFallback = $this->puzzle(['date' => now()->subDay()->toDateString(), 'product_name' => 'Fallback Active Gadget']);

        $this->assertSame($activeFallback->id, DropPrice::today()?->id);

        $this->get('/drop-price')
            ->assertOk()
            ->assertSee('Stale Gadget')
            ->assertDontSee('Fallback Active Gadget');
    }

    public function test_index_paginates_at_24(): void
    {
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Active Today']); // excluded

        for ($i = 1; $i <= 30; $i++) {
            $this->puzzle(['date' => now()->subDays($i)->toDateString(), 'product_name' => "Gadget {$i}"]);
        }

        $this->get('/drop-price')->assertOk()->assertViewHas('puzzles', fn ($p) => $p->count() === 24);
        $this->get('/drop-price?page=2')->assertOk()->assertViewHas('puzzles', fn ($p) => $p->count() === 6);
    }

    public function test_index_is_indexable(): void
    {
        $this->get('/drop-price')
            ->assertOk()
            ->assertDontSee('noindex', false);
    }

    public function test_index_renders_with_no_puzzles_yet(): void
    {
        $this->get('/drop-price')
            ->assertOk()
            ->assertSee('No past drops yet');
    }

    // ── Show ───────────────────────────────────────────────────────

    public function test_show_renders_a_past_locked_puzzle_without_leaking_the_price(): void
    {
        // A decoy dated today so the puzzle under test isn't itself mistaken for
        // the currently-active puzzle via the cron-gap fallback.
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Decoy Active Today']);

        $puzzle = $this->puzzle([
            'date'         => now()->subDay()->toDateString(),
            'price'        => 4242,
            'product_name' => 'Secret Archive Widget',
        ]);

        $this->get("/drop-price/{$puzzle->puzzle_number}")
            ->assertOk()
            ->assertSee('Secret Archive Widget')
            ->assertSee('#' . $puzzle->puzzle_number)
            ->assertDontSee('4242')
            ->assertDontSee('4,242');
    }

    public function test_show_404s_for_an_unknown_puzzle_number(): void
    {
        $this->get('/drop-price/999999')->assertNotFound();
    }

    public function test_show_404s_for_an_anomalous_unlocked_row(): void
    {
        // Should never happen via the lock command (numbers are only assigned at
        // lock time), but the controller guard must hold regardless.
        $puzzle = $this->puzzle(['date' => now()->subDay()->toDateString(), 'locked_at' => null]);

        $this->get("/drop-price/{$puzzle->puzzle_number}")->assertNotFound();
    }

    public function test_show_404s_for_a_future_dated_locked_row(): void
    {
        // Defensive parity with DropPrice::today(): even a locked row dated in
        // the future must not be playable early — its price is still the secret
        // answer for a day that hasn't happened.
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Decoy Active Today']);
        $future = $this->puzzle(['date' => now()->addDay()->toDateString(), 'locked_at' => now()]);

        $this->get("/drop-price/{$future->puzzle_number}")->assertNotFound();
    }

    public function test_show_redirects_to_home_for_the_currently_active_puzzle(): void
    {
        $active = $this->puzzle(['date' => now()->toDateString()]);

        $this->get("/drop-price/{$active->puzzle_number}")->assertRedirect(route('home'));
    }

    public function test_show_is_noindex(): void
    {
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Decoy Active Today']);
        $puzzle = $this->puzzle(['date' => now()->subDay()->toDateString()]);

        $this->get("/drop-price/{$puzzle->puzzle_number}")
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_show_wires_isarchive_into_the_livewire_component(): void
    {
        $this->puzzle(['date' => now()->toDateString(), 'product_name' => 'Decoy Active Today']);
        $puzzle = $this->puzzle(['date' => now()->subDay()->toDateString()]);

        $this->get("/drop-price/{$puzzle->puzzle_number}")
            ->assertOk()
            ->assertSee('isArchive: true', false);
    }
}
