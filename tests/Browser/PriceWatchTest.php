<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Post;
use App\Models\PriceWatch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\URL;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser coverage for the post-purchase price watch. Local run (two terminals
 * from the project root): `php artisan serve` in one, `php artisan dusk` in the
 * other — see tests/Browser/SmokeTest.php for the full env-swap explanation.
 * Uses the sqlite FILE database (database/dusk.sqlite) + DatabaseTruncation;
 * never MySQL. Mail is the array transport (.env.dusk.local), so signups send
 * nothing real — the UI state + DB row are the assertions.
 */
class PriceWatchTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** Seed a published review with a priced product (Model::create convention). */
    private function seedReview(string $slug): array
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Dusk Watch Widget',
            'asin' => 'B00DUSKW1',
            'affiliate_url' => 'https://www.amazon.com/dp/B00DUSKW1',
            'price' => 100,
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Dusk Watch Widget Review',
            'slug' => $slug,
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return [$post, $product];
    }

    /**
     * Visit once to get an origin, store the consent choice, then re-visit so
     * the banner never mounts (its Alpine init only shows without the key).
     */
    private function dismissCookieBanner(Browser $browser, string $path): Browser
    {
        $browser->visit($path);
        $browser->script("localStorage.setItem('gadgetdrop_consent', 'accepted')");

        return $browser->refresh();
    }

    public function test_a_reader_can_set_up_a_watch_from_a_review(): void
    {
        [$post, $product] = $this->seedReview('dusk-watch-signup');

        $this->browse(function (Browser $browser) use ($post) {
            // Pre-accept cookie consent: the fixed banner floats over the bottom
            // viewport edge, exactly where a scrolled-to element can land, and
            // would intercept the submit click. (The widget heading is CSS-
            // uppercased, so assertions target untransformed copy — Selenium's
            // getText() returns RENDERED text.)
            $this->dismissCookieBanner($browser, '/posts/'.$post->slug)
                ->assertSee('Compared against our tracked price')
                ->type('@watch-email', 'dusk-buyer@example.test')
                // The anti-bot gate rejects sub-3-second submits — behave like a human.
                ->pause(3200)
                ->waitForLivewire(fn (Browser $b) => $b->click('@watch-submit'))
                ->waitForText('Check your email');
        });

        $watch = PriceWatch::where('email', 'dusk-buyer@example.test')->sole();
        $this->assertSame($product->id, $watch->product_id);
        $this->assertNull($watch->verified_at);
    }

    public function test_an_existing_watch_gets_the_already_watching_state(): void
    {
        [$post, $product] = $this->seedReview('dusk-watch-duplicate');

        PriceWatch::forceCreate([
            'product_id' => $product->id,
            'email' => 'dusk-dupe@example.test',
            'purchase_price' => 100,
            'purchased_at' => today(),
            'verified_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($post) {
            $this->dismissCookieBanner($browser, '/posts/'.$post->slug)
                ->type('@watch-email', 'dusk-dupe@example.test')
                ->pause(3200)
                ->waitForLivewire(fn (Browser $b) => $b->click('@watch-submit'))
                ->waitForText('already watching this one');
        });

        $this->assertSame(1, PriceWatch::where('email', 'dusk-dupe@example.test')->count());
    }

    public function test_the_signed_verify_link_renders_the_confirmation_page(): void
    {
        [, $product] = $this->seedReview('dusk-watch-verify');

        $watch = PriceWatch::forceCreate([
            'product_id' => $product->id,
            'email' => 'dusk-verify@example.test',
            'purchase_price' => 100,
            'purchased_at' => today(),
        ]);

        // Same APP_KEY as the serve process (the Dusk env swap), so a URL signed
        // here validates there.
        $url = URL::temporarySignedRoute('watch.verify', now()->addHours(48), ['token' => $watch->token]);

        $this->browse(function (Browser $browser) use ($url) {
            $browser->visit($url)
                ->assertSee("You're set")
                ->assertSee('Dusk Watch Widget');
        });

        $this->assertNotNull($watch->fresh()->verified_at);
    }
}
