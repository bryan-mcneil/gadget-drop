<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\DropPricePuzzle;
use App\Models\DropPriceResult;
use App\Models\Product;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function puzzle(array $overrides = []): DropPricePuzzle
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Admin Mystery Widget',
            'affiliate_url' => 'https://www.amazon.com/dp/B00ADMIN',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 4242,
            'description' => 'A test gadget.',
        ]);

        return DropPricePuzzle::create(array_merge([
            'puzzle_number' => 7,
            'date' => now()->toDateString(),
            'product_id' => $product->id,
            'price' => 4242,
            'product_name' => $product->name,
            'product_image_url' => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at' => now(),
        ], $overrides));
    }

    public function test_dashboard_surfaces_todays_drop_price_with_answer_and_counts(): void
    {
        $puzzle = $this->puzzle();

        // One win, one loss → 2 plays / 1 win.
        $subA = Subscriber::create(['email' => 'a@example.com', 'token' => 'tok-a']);
        $subB = Subscriber::create(['email' => 'b@example.com', 'token' => 'tok-b']);
        DropPriceResult::create([
            'drop_price_puzzle_id' => $puzzle->id, 'subscriber_id' => $subA->id,
            'won' => true, 'guesses_used' => 2, 'played_on' => now()->toDateString(),
        ]);
        DropPriceResult::create([
            'drop_price_puzzle_id' => $puzzle->id, 'subscriber_id' => $subB->id,
            'won' => false, 'guesses_used' => 4, 'played_on' => now()->toDateString(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('dropPrice.today.number', 7)
                ->where('dropPrice.today.name', 'Admin Mystery Widget')
                ->where('dropPrice.today.price', 4242) // admin-only: the answer IS shown here
                ->where('dropPrice.today.plays', 2)
                ->where('dropPrice.today.wins', 1)
                ->where('dropPrice.upcoming', null)
            );
    }

    public function test_dashboard_has_no_drop_price_when_none_locked(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dropPrice.today', null)
                ->where('dropPrice.upcoming', null)
            );
    }

    public function test_dashboard_surfaces_a_queued_future_preset(): void
    {
        $this->puzzle(); // today's live puzzle

        // A future-dated preset queued via the CLI override (still unlocked → no number).
        $this->puzzle([
            'puzzle_number' => null,
            'date' => now()->addDay()->toDateString(),
            'locked_at' => null,
            'is_preset' => true,
            'product_name' => 'Tomorrow Gizmo',
            'price' => 99,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dropPrice.today.number', 7)
                ->where('dropPrice.upcoming.name', 'Tomorrow Gizmo')
                ->where('dropPrice.upcoming.is_preset', true)
            );
    }
}
