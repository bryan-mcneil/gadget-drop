<?php

namespace Tests\Feature\Livewire;

use App\Livewire\DropPrice;
use App\Models\Category;
use App\Models\DropPricePuzzle;
use App\Models\Product;
use App\Models\Subscriber;
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
            'category_id'   => $category->id,
            'name'          => 'Mystery Gizmo',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url'     => 'https://example.com/img.jpg',
            'price'         => $price,
            'description'   => 'A test gadget.',
        ]);

        return DropPricePuzzle::create([
            'puzzle_number'        => 1,
            'date'                 => now()->toDateString(),
            'product_id'           => $product->id,
            'price'                => $price,
            'product_name'         => $product->name,
            'product_image_url'    => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at'            => now(),
        ]);
    }

    private function mountFor(DropPricePuzzle $puzzle)
    {
        return Livewire::test(DropPrice::class, [
            'number' => $puzzle->puzzle_number,
            'name'   => $puzzle->product_name,
            'image'  => $puzzle->product_image_url,
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
            ->call('guess')
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
            ->call('guess')
            ->assertSet('won', true)
            ->assertSet('finished', true)
            ->assertSet('revealPrice', self::ANSWER)
            ->assertDispatched('dropprice-finished')
            ->assertSee('$' . self::ANSWER)
            ->assertSee(route('affiliate.redirect', $puzzle->affiliate_product_id))
            ->assertSee('nofollow sponsored', false);
    }

    public function test_four_wrong_guesses_ends_the_game_and_reveals_the_price(): void
    {
        $puzzle = $this->lockedPuzzle();

        $component = $this->mountFor($puzzle);

        foreach ([100, 150, 200, 250] as $g) {
            $component->set('guess', $g)->call('guess');
        }

        $component
            ->assertSet('finished', true)
            ->assertSet('won', false)
            ->assertSet('revealPrice', self::ANSWER)
            ->assertSee('$' . self::ANSWER);
    }

    public function test_guessing_after_the_game_is_finished_is_a_no_op(): void
    {
        $puzzle = $this->lockedPuzzle();

        $component = $this->mountFor($puzzle)
            ->set('guess', self::ANSWER)
            ->call('guess')                 // wins, finished
            ->set('guess', 1)
            ->call('guess');                // ignored

        $this->assertCount(1, $component->get('results'));
    }

    public function test_zero_or_negative_guesses_fail_validation(): void
    {
        $puzzle = $this->lockedPuzzle();

        $this->mountFor($puzzle)
            ->set('guess', 0)
            ->call('guess')
            ->assertHasErrors(['guess'])
            ->assertSet('finished', false);
    }

    public function test_saving_a_valid_email_subscribes_and_records_the_result(): void
    {
        $puzzle = $this->lockedPuzzle();

        $this->mountFor($puzzle)
            ->set('guess', self::ANSWER)
            ->call('guess')
            ->set('email', 'player@example.com')
            ->call('save', ['playStreak' => 3, 'bestPlayStreak' => 5, 'totalPlays' => 9, 'totalWins' => 4])
            ->assertSet('saveStatus', 'success');

        $subscriber = Subscriber::where('email', 'player@example.com')->sole();
        $this->assertSame(3, (int) $subscriber->play_streak);
        $this->assertSame(5, (int) $subscriber->best_play_streak);

        $this->assertDatabaseHas('drop_price_results', [
            'subscriber_id'        => $subscriber->id,
            'drop_price_puzzle_id' => $puzzle->id,
            'won'                  => true,
            'guesses_used'         => 1,
        ]);
    }

    public function test_a_duplicate_email_syncs_without_creating_a_second_subscriber(): void
    {
        $puzzle = $this->lockedPuzzle();
        Subscriber::create(['email' => 'dupe@example.com', 'token' => 'x', 'ip_address' => '127.0.0.1']);

        $this->mountFor($puzzle)
            ->set('guess', 100)->call('guess')
            ->set('guess', 120)->call('guess')
            ->set('guess', 140)->call('guess')
            ->set('guess', 160)->call('guess')   // 4 wrong → finished, lost
            ->set('email', 'dupe@example.com')
            ->call('save')
            ->assertSet('saveStatus', 'duplicate');

        $this->assertSame(1, Subscriber::where('email', 'dupe@example.com')->count());
        $this->assertDatabaseHas('drop_price_results', [
            'drop_price_puzzle_id' => $puzzle->id,
            'won'                  => false,
            'guesses_used'         => 4,
        ]);
    }
}
