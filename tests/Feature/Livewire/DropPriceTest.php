<?php

namespace Tests\Feature\Livewire;

use App\Livewire\DropPrice;
use App\Models\Category;
use App\Models\DropPricePuzzle;
use App\Models\Product;
use App\Models\Subscriber;
use App\Support\DropPrice as DropPriceGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DropPriceTest extends TestCase
{
    use RefreshDatabase;

    /** A distinctive answer that can't collide with markup numbers (e.g. h-20). */
    private const ANSWER = 347;

    private function lockedPuzzle(int $price = self::ANSWER): DropPricePuzzle
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Mystery Gizmo',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $price,
            'description' => 'A test gadget.',
        ]);

        return DropPricePuzzle::create([
            'puzzle_number' => 1,
            'date' => now()->toDateString(),
            'product_id' => $product->id,
            'price' => $price,
            'product_name' => $product->name,
            'product_image_url' => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at' => now(),
        ]);
    }

    private function mountFor(DropPricePuzzle $puzzle)
    {
        return Livewire::test(DropPrice::class, [
            'number' => $puzzle->puzzle_number,
            'name' => $puzzle->product_name,
            'image' => $puzzle->product_image_url,
        ]);
    }

    private function mountArchive(DropPricePuzzle $puzzle)
    {
        return Livewire::test(DropPrice::class, [
            'number' => $puzzle->puzzle_number,
            'name' => $puzzle->product_name,
            'image' => $puzzle->product_image_url,
            'puzzleId' => $puzzle->id,
        ]);
    }

    public function test_mount_shows_display_facts_but_never_the_price(): void
    {
        $puzzle = $this->lockedPuzzle();

        $this->mountFor($puzzle)
            ->assertSee('Mystery Gizmo')
            ->assertSee('#1')
            ->assertDontSee((string) self::ANSWER)
            ->assertSet('revealPrice', null);
    }

    public function test_a_wrong_guess_records_an_ordinal_result_and_keeps_the_price_hidden(): void
    {
        $puzzle = $this->lockedPuzzle();

        $component = $this->mountFor($puzzle)
            ->set('guess', 100)          // far below 347 → freezing, aim higher
            ->call('submitGuess')
            ->assertSet('finished', false)
            ->assertDontSee((string) self::ANSWER)
            ->assertSee('aim higher');

        $results = $component->get('results');
        $this->assertCount(1, $results);
        $this->assertSame(['guess', 'direction', 'band'], array_keys($results[0]));
        $this->assertArrayNotHasKey('price', $results[0]);
    }

    public function test_an_exact_guess_wins_reveals_the_price_and_shows_the_cta(): void
    {
        $puzzle = $this->lockedPuzzle();

        $this->mountFor($puzzle)
            ->set('guess', self::ANSWER)
            ->call('submitGuess')
            ->assertSet('won', true)
            ->assertSet('finished', true)
            ->assertSet('revealPrice', self::ANSWER)
            ->assertDispatched('dropprice-finished')
            ->assertSee('$'.self::ANSWER)
            ->assertSee(route('affiliate.redirect', $puzzle->affiliate_product_id))
            ->assertSee('nofollow sponsored', false);
    }

    public function test_exhausting_all_guesses_ends_the_game_and_reveals_the_price(): void
    {
        $puzzle = $this->lockedPuzzle();

        $component = $this->mountFor($puzzle);

        // All wrong (well below 347); the game must stay live until the final one.
        for ($i = 1; $i <= DropPriceGame::MAX_GUESSES; $i++) {
            if ($i === DropPriceGame::MAX_GUESSES) {
                $component->assertSet('finished', false);
            }
            $component->set('guess', 100 + 10 * $i)->call('submitGuess');
        }

        $component
            ->assertSet('finished', true)
            ->assertSet('won', false)
            ->assertSet('revealPrice', self::ANSWER)
            ->assertSee('$'.self::ANSWER);
    }

    public function test_guessing_after_the_game_is_finished_is_a_no_op(): void
    {
        $puzzle = $this->lockedPuzzle();

        $component = $this->mountFor($puzzle)
            ->set('guess', self::ANSWER)
            ->call('submitGuess')                 // wins, finished
            ->set('guess', 1)
            ->call('submitGuess');                // ignored

        $this->assertCount(1, $component->get('results'));
    }

    public function test_zero_or_negative_guesses_fail_validation(): void
    {
        $puzzle = $this->lockedPuzzle();

        $this->mountFor($puzzle)
            ->set('guess', 0)
            ->call('submitGuess')
            ->assertHasErrors(['guess'])
            ->assertSet('finished', false);
    }

    public function test_saving_a_valid_email_subscribes_and_records_the_result(): void
    {
        $puzzle = $this->lockedPuzzle();

        $this->mountFor($puzzle)
            ->set('guess', self::ANSWER)
            ->call('submitGuess')
            ->set('email', 'player@example.com')
            ->call('save', ['playStreak' => 3, 'bestPlayStreak' => 5, 'totalPlays' => 9, 'totalWins' => 4])
            ->assertSet('saveStatus', 'success');

        $subscriber = Subscriber::where('email', 'player@example.com')->sole();
        $this->assertSame(3, (int) $subscriber->play_streak);
        $this->assertSame(5, (int) $subscriber->best_play_streak);

        $this->assertDatabaseHas('drop_price_results', [
            'subscriber_id' => $subscriber->id,
            'drop_price_puzzle_id' => $puzzle->id,
            'won' => true,
            'guesses_used' => 1,
        ]);
    }

    public function test_a_duplicate_email_syncs_without_creating_a_second_subscriber(): void
    {
        $puzzle = $this->lockedPuzzle();
        Subscriber::create(['email' => 'dupe@example.com', 'token' => 'x', 'ip_address' => '127.0.0.1']);

        $component = $this->mountFor($puzzle);

        for ($i = 1; $i <= DropPriceGame::MAX_GUESSES; $i++) {   // all wrong → finished, lost
            $component->set('guess', 100 + 10 * $i)->call('submitGuess');
        }

        $component
            ->set('email', 'dupe@example.com')
            ->call('save')
            ->assertSet('saveStatus', 'duplicate');

        $this->assertSame(1, Subscriber::where('email', 'dupe@example.com')->count());
        $this->assertDatabaseHas('drop_price_results', [
            'drop_price_puzzle_id' => $puzzle->id,
            'won' => false,
            'guesses_used' => DropPriceGame::MAX_GUESSES,
        ]);
    }

    /** A locked puzzle at an explicit number/date/price, for archive-vs-today tests. */
    private function puzzleAt(int $number, string $date, int $price): DropPricePuzzle
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => "Archive Gizmo {$number}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $price,
            'description' => 'A test gadget.',
        ]);

        return DropPricePuzzle::create([
            'puzzle_number' => $number,
            'date' => $date,
            'product_id' => $product->id,
            'price' => $price,
            'product_name' => $product->name,
            'product_image_url' => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at' => now(),
        ]);
    }

    public function test_mounting_with_a_puzzle_id_scores_against_that_puzzle_not_today(): void
    {
        $todayPrice = 829;
        $archivePrice = 613;

        $today = $this->puzzleAt(2, now()->toDateString(), $todayPrice);
        $archive = $this->puzzleAt(1, now()->subDay()->toDateString(), $archivePrice);

        $this->mountArchive($archive)
            ->set('guess', $archivePrice)
            ->call('submitGuess')
            ->assertSet('won', true)
            ->assertSet('revealPrice', $archivePrice)
            ->assertDontSee((string) $todayPrice);

        $this->assertNotSame($today->id, $archive->id);
    }

    public function test_a_puzzle_id_pointing_at_an_unlocked_preset_is_a_safe_no_op(): void
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Future Gizmo',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 999,
            'description' => 'A test gadget.',
        ]);

        // Queued future preset — has a product/price but is deliberately unlocked.
        $preset = DropPricePuzzle::create([
            'puzzle_number' => null,
            'date' => now()->addDays(2)->toDateString(),
            'product_id' => $product->id,
            'price' => 999,
            'product_name' => $product->name,
            'product_image_url' => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at' => null,
            'is_preset' => true,
        ]);

        $component = Livewire::test(DropPrice::class, [
            'number' => 1,
            'name' => 'Future Gizmo',
            'image' => $product->image_url,
            'puzzleId' => $preset->id,
        ])
            ->set('guess', 999)
            ->call('submitGuess')
            ->assertSet('finished', false)
            ->assertDontSee('999');

        $this->assertCount(0, $component->get('results'));
    }

    public function test_save_with_a_puzzle_id_records_the_result_against_that_puzzle_not_today(): void
    {
        $today = $this->puzzleAt(2, now()->toDateString(), 829);
        $archive = $this->puzzleAt(1, now()->subDay()->toDateString(), 613);

        $this->mountArchive($archive)
            ->set('guess', 613)
            ->call('submitGuess')
            ->set('email', 'archive-player@example.com')
            ->call('save')
            ->assertSet('saveStatus', 'success');

        $subscriber = Subscriber::where('email', 'archive-player@example.com')->sole();

        $this->assertDatabaseHas('drop_price_results', [
            'subscriber_id' => $subscriber->id,
            'drop_price_puzzle_id' => $archive->id,
            'won' => true,
        ]);
        $this->assertDatabaseMissing('drop_price_results', [
            'subscriber_id' => $subscriber->id,
            'drop_price_puzzle_id' => $today->id,
        ]);
    }
}
