<?php

namespace Tests\Feature;

use App\Mail\WatchClosingMail;
use App\Mail\WatchDropMail;
use App\Models\Category;
use App\Models\Post;
use App\Models\PriceWatch;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckWatchesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-22 10:00:00');
        Mail::fake();
    }

    private function makeProduct(?float $price = 100, array $overrides = []): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => "Command Widget {$i}",
            'asin' => "B00CMD{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00CMD',
            'price' => $price,
        ], $overrides));
    }

    /** Verified, in-window watch by default; override to build the skip cases. */
    private function makeWatch(Product $product, array $overrides = []): PriceWatch
    {
        return PriceWatch::forceCreate(array_merge([
            'product_id' => $product->id,
            'email' => 'buyer@example.test',
            'purchase_price' => 100,
            'purchased_at' => today(),
            'verified_at' => now(),
        ], $overrides));
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

    public function test_a_drop_past_the_threshold_sends_one_alert_and_stamps_the_watch(): void
    {
        $watch = $this->makeWatch($this->makeProduct(77)); // paid 100 → drop 23

        $this->artisan('watches:check')->assertExitCode(0);

        Mail::assertSent(WatchDropMail::class, fn ($mail) => $mail->hasTo('buyer@example.test'));
        Mail::assertSentCount(1);
        $this->assertNotNull($watch->fresh()->notified_at);
    }

    public function test_a_drop_below_the_threshold_sends_nothing(): void
    {
        $watch = $this->makeWatch($this->makeProduct(96)); // drop 4 < $5 floor

        $this->artisan('watches:check')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertNull($watch->fresh()->notified_at);
    }

    public function test_the_absolute_floor_governs_cheap_products(): void
    {
        // paid 100: 3% is $3, so the $5 absolute floor is the bar.
        $this->makeWatch($this->makeProduct(94.5), ['email' => 'over@example.test']);

        $this->artisan('watches:check');

        Mail::assertSent(WatchDropMail::class, fn ($mail) => $mail->hasTo('over@example.test'));
        Mail::assertSentCount(1);
    }

    public function test_the_percentage_floor_governs_expensive_products(): void
    {
        // paid 500: the bar is 3% = $15, not $5. A $12 drop is noise on a $500 buy.
        $under = $this->makeWatch($this->makeProduct(488), ['purchase_price' => 500, 'email' => 'under@example.test']);
        $this->makeWatch($this->makeProduct(484), ['purchase_price' => 500, 'email' => 'over@example.test']);

        $this->artisan('watches:check');

        Mail::assertSent(WatchDropMail::class, fn ($mail) => $mail->hasTo('over@example.test'));
        Mail::assertSentCount(1);
        $this->assertNull($under->fresh()->notified_at);
    }

    public function test_unverified_expired_notified_and_priceless_watches_are_all_skipped(): void
    {
        $this->makeWatch($this->makeProduct(50), ['verified_at' => null, 'email' => 'unverified@example.test']);
        $this->makeWatch($this->makeProduct(50), ['purchased_at' => now()->subDays(40), 'email' => 'expired@example.test']);
        $this->makeWatch($this->makeProduct(50), ['notified_at' => now()->subDay(), 'email' => 'notified@example.test']);
        $this->makeWatch($this->makeProduct(null), ['email' => 'priceless@example.test']);
        $this->makeWatch($this->makeProduct(50), ['email' => 'eligible@example.test']);

        $this->artisan('watches:check');

        Mail::assertSent(WatchDropMail::class, fn ($mail) => $mail->hasTo('eligible@example.test'));
        Mail::assertSentCount(1);
    }

    public function test_a_second_run_sends_nothing(): void
    {
        $this->makeWatch($this->makeProduct(77));

        $this->artisan('watches:check');
        $this->artisan('watches:check');

        Mail::assertSentCount(1);
    }

    public function test_the_drop_mail_spells_out_savings_and_days_left(): void
    {
        // Paid $100, now $77, purchased 25 days ago → $23.00 back, 5 days left.
        $watch = $this->makeWatch($this->makeProduct(77), ['purchased_at' => now()->subDays(25)]);

        $html = (new WatchDropMail($watch))->render();

        $this->assertStringContainsString('$23.00', $html);
        $this->assertStringContainsString('5 days', $html);
        $this->assertStringContainsString('Free Returns', $html);
        // The staleness-honesty line: prices are ours, dated.
        $this->assertStringContainsString('our last check', $html);
        // Review-page CTA only — never an /out affiliate link in mail.
        $this->assertStringNotContainsString('/out/', $html);
    }

    public function test_watch_mails_link_the_product_reviews_page_when_one_exists(): void
    {
        $product = $this->makeProduct(77);
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Command Widget Review',
            'slug' => 'command-widget-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        $html = (new WatchDropMail($this->makeWatch($product)))->render();

        $this->assertStringContainsString('/posts/command-widget-review', $html);
    }

    public function test_closing_summary_goes_out_inside_the_notice_window_and_stamps(): void
    {
        // Expires in exactly 3 days (the config edge) — price never dropped.
        $edge = $this->makeWatch($this->makeProduct(100), ['purchased_at' => now()->subDays(27), 'email' => 'edge@example.test']);
        // Expires today — the last chance to say goodbye.
        $today = $this->makeWatch($this->makeProduct(100), ['purchased_at' => now()->subDays(30), 'email' => 'today@example.test']);
        // Expires in 4 days — not yet.
        $early = $this->makeWatch($this->makeProduct(100), ['purchased_at' => now()->subDays(26), 'email' => 'early@example.test']);

        $this->artisan('watches:check')->assertExitCode(0);

        Mail::assertSent(WatchClosingMail::class, fn ($mail) => $mail->hasTo('edge@example.test'));
        Mail::assertSent(WatchClosingMail::class, fn ($mail) => $mail->hasTo('today@example.test'));
        Mail::assertSentCount(2);
        $this->assertNotNull($edge->fresh()->closing_mail_sent_at);
        $this->assertNotNull($today->fresh()->closing_mail_sent_at);
        $this->assertNull($early->fresh()->closing_mail_sent_at);
    }

    public function test_closing_summary_is_sent_once_and_never_to_unverified_watches(): void
    {
        $this->makeWatch($this->makeProduct(100), ['purchased_at' => now()->subDays(28)]);
        $this->makeWatch($this->makeProduct(100), ['purchased_at' => now()->subDays(28), 'verified_at' => null, 'email' => 'unverified@example.test']);

        $this->artisan('watches:check');
        $this->artisan('watches:check');

        Mail::assertSent(WatchClosingMail::class, fn ($mail) => $mail->hasTo('buyer@example.test'));
        Mail::assertSentCount(1);
    }

    public function test_a_drop_alert_preempts_the_closing_summary(): void
    {
        // In the closing window AND past the drop threshold: the reader gets the
        // actionable mail, never both.
        $watch = $this->makeWatch($this->makeProduct(80), ['purchased_at' => now()->subDays(28)]);

        $this->artisan('watches:check');

        Mail::assertSent(WatchDropMail::class, fn ($mail) => $mail->hasTo('buyer@example.test'));
        Mail::assertNotSent(WatchClosingMail::class);
        Mail::assertSentCount(1);
        $this->assertNull($watch->fresh()->closing_mail_sent_at);

        $this->artisan('watches:check');
        Mail::assertSentCount(1);
    }

    public function test_the_closing_mail_carries_a_verdict_when_stats_allow_one(): void
    {
        // Held at $100 for 60 days → stats gate open, paid $100 → "typical".
        $product = $this->makeProduct(100);
        $this->snapshot($product, 100, 60);
        $this->snapshot($product, 100, 30);
        $watch = $this->makeWatch($product, ['purchased_at' => now()->subDays(28)]);

        $html = (new WatchClosingMail($watch))->render();

        $this->assertStringContainsString('typical price', $html);
    }

    public function test_the_closing_mail_is_honest_when_history_is_too_thin_to_judge(): void
    {
        // Only the creation snapshot — PriceIntel's honesty gate stays closed.
        $watch = $this->makeWatch($this->makeProduct(100), ['purchased_at' => now()->subDays(28)]);

        $html = (new WatchClosingMail($watch))->render();

        $this->assertStringContainsString('enough tracked history', $html);
    }

    public function test_the_closing_mail_verdict_tiers_classify_what_the_reader_paid(): void
    {
        // Held flat at $100 for 60 days → avg90 = 100 exactly.
        $flat = $this->makeProduct(100);
        $this->snapshot($flat, 100, 60);
        $this->snapshot($flat, 100, 30);

        $good = (new WatchClosingMail($this->makeWatch($flat, ['purchase_price' => 90])))->render();
        $elevated = (new WatchClosingMail($this->makeWatch($flat, ['purchase_price' => 110])))->render();

        $this->assertStringContainsString('better-than-typical', $good);
        $this->assertStringContainsString('above its typical', $elevated);

        // $120 for two months then $100 → variation exists, and $100 is the
        // 90-day low: a reader who paid $100 caught the lowest tracked price.
        $dropped = $this->makeProduct(100);
        $this->snapshot($dropped, 120, 60);
        $this->snapshot($dropped, 100, 30);

        $lowest = (new WatchClosingMail($this->makeWatch($dropped, ['purchase_price' => 100])))->render();

        $this->assertStringContainsString('lowest price we have tracked', $lowest);
    }
}
