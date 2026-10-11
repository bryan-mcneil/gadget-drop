<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\TruthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Event window under test: 2026-07-07 .. 2026-07-08 (inclusive).
 * Baseline window (30 days before): 2026-06-07 .. 2026-07-06.
 * Insufficient-history gate (14 days): first snapshot must be on or before 2026-06-23.
 */
class TruthReportTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-07-07';

    private const TO = '2026-07-08';

    private const ARTIFACT = 'app/truth/phpunit-truth.json';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-01 09:00:00');
        File::delete(storage_path(self::ARTIFACT));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path(self::ARTIFACT));

        parent::tearDown();
    }

    /**
     * @param  array<string, float>  $snapshots  date => price
     */
    private function trackedProduct(array $snapshots, string $name = 'Widget'): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => "{$name} {$i}",
            'asin' => "B00TRUTH{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00TRUTH',
            'price' => 100,
        ]);

        // Drop the observer's creation snapshot (stamped at test-now) so the
        // fixture controls the entire history.
        $product->priceSnapshots()->delete();

        foreach ($snapshots as $date => $price) {
            ProductPriceSnapshot::create([
                'product_id' => $product->id,
                'price' => $price,
                'source' => 'manual',
                'created_at' => Carbon::parse($date),
            ]);
        }

        return $product;
    }

    private function analyze(): array
    {
        return TruthReport::analyze(Carbon::parse(self::FROM), Carbon::parse(self::TO));
    }

    private function rowFor(array $report, Product $product): array
    {
        foreach ($report['products'] as $row) {
            if ($row['product_id'] === $product->id) {
                return $row;
            }
        }

        $this->fail("No report row for product {$product->id}.");
    }

    public function test_a_genuine_event_discount_is_a_real_deal(): void
    {
        $product = $this->trackedProduct([
            '2026-05-01' => 100.00,
            self::FROM => 89.99,
        ]);

        $row = $this->rowFor($this->analyze(), $product);

        $this->assertSame('real_deal', $row['classification']);
        $this->assertSame(100.0, $row['pre_min']);
        $this->assertSame(100.0, $row['pre_avg']);
        $this->assertSame(89.99, $row['event_min']);
        $this->assertSame(10.0, $row['discount_pct']);
        $this->assertSame('2026-05-01', $row['tracked_since']);
    }

    public function test_classification_thresholds_at_their_boundaries(): void
    {
        $atRealDealLine = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 95.00]);
        $justAboveLine = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 95.01]);
        $atWorseLine = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 105.00]);
        $justAboveWorse = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 105.01]);

        $report = $this->analyze();

        $this->assertSame('real_deal', $this->rowFor($report, $atRealDealLine)['classification']);
        $this->assertSame('repackaged', $this->rowFor($report, $justAboveLine)['classification']);
        $this->assertSame('repackaged', $this->rowFor($report, $atWorseLine)['classification']);
        $this->assertSame('worse', $this->rowFor($report, $justAboveWorse)['classification']);
    }

    public function test_a_snapshot_before_the_baseline_window_carries_in(): void
    {
        // 120 was superseded by 100 the day before the baseline window opens,
        // so the baseline must be a flat 100 — the 120 never appears. With no
        // snapshot recorded inside the event window the row is unobserved
        // (5.5 gate — this fixture was judged "repackaged" before it).
        $product = $this->trackedProduct([
            '2026-05-20' => 120.00,
            '2026-06-06' => 100.00,
        ]);

        $row = $this->rowFor($this->analyze(), $product);

        $this->assertSame(100.0, $row['pre_min']);
        $this->assertSame(100.0, $row['pre_avg']);
        $this->assertSame('unobserved', $row['classification']);
        $this->assertNull($row['event_min']);
        $this->assertNull($row['discount_pct']);
    }

    public function test_the_baseline_average_weights_a_price_by_days_held(): void
    {
        // Baseline: 15 days at 100 (06-07..06-21), then 15 days at 90 — avg 95.
        // Nothing recorded inside the event window, so no event verdict (5.5
        // gate — this fixture was judged "repackaged" before it).
        $product = $this->trackedProduct([
            '2026-05-01' => 100.00,
            '2026-06-22' => 90.00,
        ]);

        $row = $this->rowFor($this->analyze(), $product);

        $this->assertSame(95.0, $row['pre_avg']);
        $this->assertSame(90.0, $row['pre_min']);
        $this->assertSame('unobserved', $row['classification']);
        $this->assertNull($row['event_min']);
    }

    public function test_a_drop_on_the_last_event_day_sets_the_event_min(): void
    {
        $product = $this->trackedProduct([
            '2026-05-01' => 100.00,
            self::TO => 79.99,
        ]);

        $row = $this->rowFor($this->analyze(), $product);

        $this->assertSame(100.0, $row['pre_min']);
        $this->assertSame(79.99, $row['event_min']);
        $this->assertSame('real_deal', $row['classification']);
        $this->assertSame(20.0, $row['discount_pct']);
    }

    public function test_the_insufficient_history_gate_and_its_boundary(): void
    {
        // First snapshot 13 days before the event: too new to judge.
        $tooNew = $this->trackedProduct(['2026-06-24' => 100.00, self::FROM => 80.00]);
        // Exactly 14 days before the event: judged.
        $justEnough = $this->trackedProduct(['2026-06-23' => 100.00, self::FROM => 80.00]);

        $report = $this->analyze();

        $tooNewRow = $this->rowFor($report, $tooNew);
        $this->assertSame('insufficient', $tooNewRow['classification']);
        $this->assertNull($tooNewRow['pre_min']);
        $this->assertNull($tooNewRow['pre_avg']);
        $this->assertNull($tooNewRow['event_min']);
        $this->assertNull($tooNewRow['discount_pct']);

        $this->assertSame('real_deal', $this->rowFor($report, $justEnough)['classification']);

        $this->assertSame(2, $report['totals']['tracked']);
        $this->assertSame(1, $report['totals']['judged']);
        $this->assertSame(0, $report['totals']['unobserved']);
        $this->assertSame(1, $report['totals']['insufficient']);
    }

    public function test_a_product_unseen_during_the_event_window_is_unobserved(): void
    {
        // Solid baseline, snapshots before AND after the window — but nothing
        // recorded inside it. Carry-forward alone must not produce a verdict;
        // the valid pre-event stats still render, the event columns stay null.
        $product = $this->trackedProduct([
            '2026-05-01' => 100.00,
            '2026-06-20' => 90.00,
            '2026-07-10' => 60.00,
        ]);

        $report = $this->analyze();
        $row = $this->rowFor($report, $product);

        $this->assertSame('unobserved', $row['classification']);
        $this->assertSame(90.0, $row['pre_min']);
        $this->assertNull($row['event_min']);
        $this->assertNull($row['discount_pct']);

        $this->assertSame(1, $report['totals']['tracked']);
        $this->assertSame(0, $report['totals']['judged']);
        $this->assertSame(1, $report['totals']['unobserved']);
        $this->assertSame(0, $report['totals']['insufficient']);
        // Zero judged: class percentages and the headline stay honest nulls.
        $this->assertNull($report['classes']['repackaged']['pct']);
        $this->assertNull($report['headline']['real_deal_pct']);
        $this->assertNull($report['headline']['median_discount_pct']);
    }

    public function test_one_event_snapshot_is_enough_to_judge(): void
    {
        // A single price recorded on the first event day: the gate does not
        // fire, and carry-forward legitimately fills the rest of the window.
        $product = $this->trackedProduct([
            '2026-05-01' => 100.00,
            self::FROM => 89.99,
        ]);

        $report = $this->analyze();

        $this->assertSame('real_deal', $this->rowFor($report, $product)['classification']);
        $this->assertSame(1, $report['totals']['judged']);
        $this->assertSame(0, $report['totals']['unobserved']);
    }

    public function test_aggregates_headline_and_row_order(): void
    {
        $bigDeal = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 70.00], 'Big Deal');
        $smallDeal = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 90.00], 'Small Deal');
        // Checked during the event, same price — the classic repackaged deal.
        $flat = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 100.00], 'Flat');
        $markedUp = $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 112.00], 'Marked Up');
        $notWatched = $this->trackedProduct(['2026-05-01' => 100.00], 'Not Watched');
        $tooNew = $this->trackedProduct(['2026-06-30' => 50.00], 'Too New');

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Big Deal Review',
            'slug' => 'big-deal-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => Carbon::parse('2026-06-01'),
        ]);
        $post->products()->attach($bigDeal->id, ['display_order' => 1]);

        $report = $this->analyze();

        $this->assertSame(6, $report['totals']['tracked']);
        $this->assertSame(4, $report['totals']['judged']);
        $this->assertSame(1, $report['totals']['unobserved']);
        $this->assertSame(1, $report['totals']['insufficient']);

        $this->assertSame(['count' => 2, 'pct' => 50.0], $report['classes']['real_deal']);
        $this->assertSame(['count' => 1, 'pct' => 25.0], $report['classes']['repackaged']);
        $this->assertSame(['count' => 1, 'pct' => 25.0], $report['classes']['worse']);

        $this->assertSame(50.0, $report['headline']['real_deal_pct']);
        $this->assertSame([
            'product' => $bigDeal->name,
            'post_slug' => 'big-deal-review',
            'discount_pct' => 30.0,
        ], $report['headline']['biggest_real_deal']);
        $this->assertSame([
            'product' => $markedUp->name,
            'post_slug' => null,
            'markup_pct' => 12.0,
        ], $report['headline']['biggest_markup']);
        // Judged discounts sorted: -12, 0, 10, 30 -> median (0 + 10) / 2 = 5.
        // The unobserved row's null discount must never enter this median.
        $this->assertSame(5.0, $report['headline']['median_discount_pct']);

        // Rows: real deals (best first), repackaged, worse, then the honesty
        // ledger — unobserved before insufficient.
        $order = array_column($report['products'], 'product_id');
        $this->assertSame([
            $bigDeal->id, $smallDeal->id, $flat->id, $markedUp->id, $notWatched->id, $tooNew->id,
        ], $order);

        $this->assertNull($this->rowFor($report, $smallDeal)['post_slug']);
    }

    public function test_a_zero_price_baseline_is_never_judged(): void
    {
        // A 0.00 baseline can't anchor a ratio — bad data is reported as
        // insufficient, never guessed at.
        $product = $this->trackedProduct(['2026-05-01' => 0.00, self::FROM => 20.00]);

        $report = $this->analyze();
        $row = $this->rowFor($report, $product);

        $this->assertSame('insufficient', $row['classification']);
        $this->assertNull($row['pre_min']);
        $this->assertNull($row['event_min']);
        $this->assertNull($row['discount_pct']);
        $this->assertSame(1, $report['totals']['insufficient']);
    }

    public function test_products_without_snapshots_stay_out_of_the_report(): void
    {
        $tracked = $this->trackedProduct(['2026-05-01' => 100.00]);

        // Unpriced product: the observer never snapshots it.
        Product::create([
            'category_id' => $tracked->category_id,
            'name' => 'Unpriced Widget',
            'affiliate_url' => 'https://www.amazon.com/dp/B00NOPRICE',
            'price' => null,
        ]);

        $report = $this->analyze();

        $this->assertSame(1, $report['totals']['tracked']);
        $this->assertSame([$tracked->id], array_column($report['products'], 'product_id'));
    }

    public function test_the_command_writes_a_valid_stable_artifact(): void
    {
        $this->trackedProduct(['2026-05-01' => 100.00, self::FROM => 70.00]);

        $this->artisan('truth:report', [
            'slug' => 'phpunit-truth',
            '--from' => self::FROM,
            '--to' => self::TO,
        ])
            ->expectsOutputToContain('Truth Report: phpunit-truth (2026-07-07 to 2026-07-08)')
            ->expectsOutputToContain('Tracked: 1 | Judged: 1 | Unobserved during event: 0 | Insufficient history: 0')
            ->assertSuccessful();

        $path = storage_path(self::ARTIFACT);
        $this->assertFileExists($path);

        $report = json_decode(File::get($path), true);
        $this->assertIsArray($report);

        // Stable key order is the artifact's diffability contract.
        $this->assertSame(
            ['slug', 'generated_at', 'window', 'baseline_days', 'config', 'totals', 'classes', 'headline', 'products'],
            array_keys($report),
        );
        $this->assertSame(
            ['tracked', 'judged', 'unobserved', 'insufficient'],
            array_keys($report['totals']),
        );
        $this->assertSame('phpunit-truth', $report['slug']);
        $this->assertSame(['from' => self::FROM, 'to' => self::TO], $report['window']);
        $this->assertSame(1, $report['totals']['tracked']);
        $this->assertEquals(30.0, $report['headline']['biggest_real_deal']['discount_pct']);
        $this->assertNotEmpty($report['generated_at']);
    }

    public function test_the_command_dry_run_writes_nothing(): void
    {
        $this->trackedProduct(['2026-05-01' => 100.00]);

        $this->artisan('truth:report', [
            'slug' => 'phpunit-truth',
            '--from' => self::FROM,
            '--to' => self::TO,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run - nothing written.')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(storage_path(self::ARTIFACT));
    }

    public function test_the_command_rejects_bad_input(): void
    {
        $this->artisan('truth:report', [
            'slug' => '../evil',
            '--from' => self::FROM,
            '--to' => self::TO,
        ])->assertFailed();

        $this->artisan('truth:report', ['slug' => 'phpunit-truth', '--to' => self::TO])
            ->assertFailed();

        $this->artisan('truth:report', [
            'slug' => 'phpunit-truth',
            '--from' => '2026-13-99',
            '--to' => self::TO,
        ])->assertFailed();

        $this->artisan('truth:report', [
            'slug' => 'phpunit-truth',
            '--from' => self::TO,
            '--to' => self::FROM,
        ])->assertFailed();

        $this->assertFileDoesNotExist(storage_path(self::ARTIFACT));
    }
}
