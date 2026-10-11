<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceWatch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The magic-link half of the price watch: signed verify + unsubscribe routes,
 * their failure modes, and the privacy-page disclosure.
 */
class PriceWatchFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeWatch(array $overrides = []): PriceWatch
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Flow Widget',
            'asin' => 'B00FLOW1',
            'affiliate_url' => 'https://www.amazon.com/dp/B00FLOW1',
            'price' => 100,
        ]);

        return PriceWatch::forceCreate(array_merge([
            'product_id' => $product->id,
            'email' => 'buyer@example.test',
            'purchase_price' => 100,
            'purchased_at' => today(),
        ], $overrides));
    }

    private function verifyUrl(PriceWatch $watch): string
    {
        return URL::temporarySignedRoute('watch.verify', now()->addHours(48), ['token' => $watch->token]);
    }

    public function test_a_signed_verify_link_activates_the_watch(): void
    {
        $watch = $this->makeWatch();

        $this->get($this->verifyUrl($watch))
            ->assertOk()
            ->assertSee("You're set")
            ->assertSee('Flow Widget', false)
            ->assertSee($watch->expires_at->format('M j, Y'), false)
            // Em-dash-free voice, matched to the emails that link here (Phase 10.5).
            ->assertSee("one email. That's the whole deal", false);

        $this->assertNotNull($watch->fresh()->verified_at);
    }

    public function test_a_second_verify_click_shows_the_already_verified_state(): void
    {
        $watch = $this->makeWatch(['verified_at' => now()->subHour()]);
        $before = $watch->verified_at;

        $this->get($this->verifyUrl($watch))
            ->assertOk()
            ->assertSee("You're already set");

        // The original verification stamp is untouched.
        $this->assertTrue($watch->fresh()->verified_at->equalTo($before));
    }

    public function test_an_expired_signature_is_rejected(): void
    {
        $watch = $this->makeWatch();
        $url = $this->verifyUrl($watch);

        $this->travel(49)->hours();

        $this->get($url)->assertForbidden();
        $this->assertNull($watch->fresh()->verified_at);
    }

    public function test_an_unsigned_verify_url_is_rejected(): void
    {
        $watch = $this->makeWatch();

        $this->get(route('watch.verify', ['token' => $watch->token]))->assertForbidden();
        $this->assertNull($watch->fresh()->verified_at);
    }

    public function test_a_validly_signed_unknown_token_is_a_404(): void
    {
        $this->makeWatch();

        $url = URL::temporarySignedRoute('watch.verify', now()->addHours(48), [
            'token' => '00000000-0000-0000-0000-000000000000',
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_the_confirmation_page_is_noindex(): void
    {
        $watch = $this->makeWatch();

        $this->get($this->verifyUrl($watch))
            ->assertOk()
            ->assertSee('noindex, follow', false);
    }

    public function test_a_signed_unsubscribe_link_deletes_the_watch_and_is_idempotent(): void
    {
        $watch = $this->makeWatch(['verified_at' => now()]);
        $url = URL::signedRoute('watch.unsubscribe', ['token' => $watch->token]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Watch removed', false)
            // Em-dash-free voice, matched to the emails that link here (Phase 10.5).
            ->assertSee('No more emails about it, ever', false);

        $this->assertDatabaseMissing('price_watches', ['id' => $watch->id]);

        // A second click on the same mail link lands on the same page, not a 404.
        $this->get($url)
            ->assertOk()
            ->assertSee('Watch removed', false);
    }

    public function test_an_unsigned_unsubscribe_url_is_rejected(): void
    {
        $watch = $this->makeWatch();

        $this->get(route('watch.unsubscribe', ['token' => $watch->token]))->assertForbidden();
        $this->assertDatabaseHas('price_watches', ['id' => $watch->id]);
    }

    public function test_the_privacy_page_discloses_the_watch_data_handling(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Price watch', false)
            ->assertSee('60 days', false)
            ->assertSee('confirmation link', false);
    }
}
