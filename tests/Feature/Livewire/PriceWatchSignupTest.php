<?php

namespace Tests\Feature\Livewire;

use App\Livewire\PriceWatchSignup;
use App\Mail\WatchVerifyMail;
use App\Models\Category;
use App\Models\Post;
use App\Models\PriceWatch;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class PriceWatchSignupTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Watched Widget',
            'asin' => 'B00WATCH1',
            'affiliate_url' => 'https://www.amazon.com/dp/B00WATCH1',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 100,
            'description' => 'A widget.',
        ], $overrides));
    }

    private function makeReview(Product $product): Post
    {
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Watched Widget Review',
            'slug' => 'watched-widget-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return $post;
    }

    /** Add a historical snapshot without touching products.price. */
    private function snapshot(Product $product, float $price, int $daysAgo): void
    {
        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => $price,
            'source' => 'manual',
            'created_at' => now()->subDays($daysAgo),
        ]);
        PriceIntel::flush($product->id);
    }

    /** Mount validly and move past the too-fast-submit bot gate. */
    private function validSubmission(Product $product)
    {
        $component = Livewire::test(PriceWatchSignup::class, ['productId' => $product->id]);

        $this->travel(10)->seconds();

        return $component->set('email', 'buyer@example.test');
    }

    public function test_the_widget_renders_on_a_review_page_with_a_tracked_price(): void
    {
        $post = $this->makeReview($this->makeProduct());

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Already bought it?', false)
            ->assertSeeLivewire(PriceWatchSignup::class);
    }

    public function test_the_widget_is_absent_when_the_product_has_no_price(): void
    {
        $post = $this->makeReview($this->makeProduct(['price' => null]));

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Already bought it?', false);
    }

    public function test_the_widget_is_absent_on_tech_tip_pages(): void
    {
        // tech_tip exercises the same skip branch as tech_news (sqlite type CHECK).
        $post = $this->makeReview($this->makeProduct());
        $post->update(['type' => 'tech_tip']);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Already bought it?', false);
    }

    public function test_email_and_date_bounds_are_validated(): void
    {
        Mail::fake();
        $product = $this->makeProduct();

        Livewire::test(PriceWatchSignup::class, ['productId' => $product->id])
            ->set('email', 'not-an-email')
            ->call('startWatch')
            ->assertHasErrors(['email']);

        Livewire::test(PriceWatchSignup::class, ['productId' => $product->id])
            ->set('email', 'buyer@example.test')
            ->set('purchased_on', today()->subDays(45)->toDateString()) // window already closed
            ->call('startWatch')
            ->assertHasErrors(['purchased_on']);

        Livewire::test(PriceWatchSignup::class, ['productId' => $product->id])
            ->set('email', 'buyer@example.test')
            ->set('purchased_on', today()->addDay()->toDateString()) // bought in the future?
            ->call('startWatch')
            ->assertHasErrors(['purchased_on']);

        Mail::assertNothingSent();
        $this->assertSame(0, PriceWatch::count());
    }

    public function test_filled_honeypot_pretends_success_but_creates_nothing(): void
    {
        Mail::fake();
        $product = $this->makeProduct();

        $this->validSubmission($product)
            ->set('company', 'Totally Real Corp')
            ->call('startWatch')
            ->assertSet('status', 'pending');

        Mail::assertNothingSent();
        $this->assertSame(0, PriceWatch::count());
    }

    public function test_instant_submit_is_treated_as_a_bot(): void
    {
        Mail::fake();
        $product = $this->makeProduct();

        // No time travel — submitted within 3s of render.
        Livewire::test(PriceWatchSignup::class, ['productId' => $product->id])
            ->set('email', 'bot@example.test')
            ->call('startWatch')
            ->assertSet('status', 'pending');

        Mail::assertNothingSent();
        $this->assertSame(0, PriceWatch::count());
    }

    public function test_signups_are_rate_limited_per_ip(): void
    {
        Mail::fake();
        $product = $this->makeProduct();

        foreach (range(1, 5) as $i) {
            RateLimiter::hit('watch-signup:127.0.0.1', 3600);
        }

        $this->validSubmission($product)
            ->call('startWatch')
            ->assertSet('status', 'throttled');

        Mail::assertNothingSent();
        $this->assertSame(0, PriceWatch::count());
    }

    public function test_an_active_duplicate_gets_the_friendly_already_watching_state(): void
    {
        Mail::fake();
        $product = $this->makeProduct();

        PriceWatch::create([
            'product_id' => $product->id,
            'email' => 'buyer@example.test',
            'purchase_price' => 100,
            'purchased_at' => today(),
        ]);

        $this->validSubmission($product)
            ->call('startWatch')
            ->assertSet('status', 'duplicate');

        Mail::assertNothingSent();
        $this->assertSame(1, PriceWatch::count());
    }

    public function test_a_valid_signup_creates_an_unverified_watch_and_mails_a_working_signed_link(): void
    {
        Mail::fake();
        $product = $this->makeProduct();

        $this->validSubmission($product)
            ->call('startWatch')
            ->assertSet('status', 'pending');

        $watch = PriceWatch::sole();
        $this->assertNull($watch->verified_at);
        $this->assertSame('buyer@example.test', $watch->email);
        $this->assertSame($product->id, $watch->product_id);
        $this->assertSame('127.0.0.1', $watch->ip_address);
        $this->assertSame(today()->toDateString(), $watch->purchased_at->toDateString());
        $this->assertSame(today()->addDays(30)->toDateString(), $watch->expires_at->toDateString());
        // Only the creation snapshot exists → the baseline is today's price.
        $this->assertSame(100.0, (float) $watch->purchase_price);

        $verifyUrl = null;
        Mail::assertSent(WatchVerifyMail::class, function (WatchVerifyMail $mail) use ($watch, &$verifyUrl) {
            $verifyUrl = $mail->verifyUrl;

            return $mail->hasTo('buyer@example.test')
                && $mail->watch->is($watch)
                && str_contains($mail->verifyUrl, 'signature=');
        });

        // The signed link actually validates end to end.
        $this->get($verifyUrl)->assertOk();
        $this->assertNotNull($watch->fresh()->verified_at);
    }

    public function test_the_purchase_baseline_is_the_tracked_price_on_the_purchase_date(): void
    {
        Mail::fake();
        // Tracking said $110 until it dropped to $100 today; a purchase 10 days
        // ago must baseline at $110 (the carry-forward price), not today's $100.
        $product = $this->makeProduct();
        $this->snapshot($product, 110, 40);

        $this->validSubmission($product)
            ->set('purchased_on', today()->subDays(10)->toDateString())
            ->call('startWatch')
            ->assertSet('status', 'pending');

        $this->assertSame(110.0, (float) PriceWatch::sole()->purchase_price);
    }

    public function test_price_on_falls_back_to_the_live_price_when_no_snapshots_exist(): void
    {
        // withoutEvents suppresses ProductObserver's creation snapshot — the
        // only way a live product can have zero history.
        $product = Product::withoutEvents(fn () => $this->makeProduct());

        $this->assertSame(0, $product->priceSnapshots()->count());
        $this->assertSame(100.0, PriceIntel::priceOn($product->id, today()));
    }

    public function test_signup_emails_are_normalized_to_lowercase(): void
    {
        Mail::fake();
        $product = $this->makeProduct();

        $this->validSubmission($product)
            ->set('email', 'Buyer@Example.TEST')
            ->call('startWatch')
            ->assertSet('status', 'pending');

        $this->assertSame('buyer@example.test', PriceWatch::sole()->email);

        // The other casing of the same address is the same reader — duplicate.
        $second = Livewire::test(PriceWatchSignup::class, ['productId' => $product->id]);
        $this->travel(10)->seconds();
        $second->set('email', 'BUYER@example.test')
            ->call('startWatch')
            ->assertSet('status', 'duplicate');
    }
}
