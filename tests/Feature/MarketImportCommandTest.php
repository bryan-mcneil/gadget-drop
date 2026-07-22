<?php

namespace Tests\Feature;

use App\Models\MarketPriceSnapshot;
use App\Models\MarketProduct;
use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Covers market:import — the CSV contract (docs/MARKET-IMPORT.md), the
 * market-wide layer's change-only snapshots, the curated-layer merge through
 * ProductObserver (source 'import'), and the JSON run artifact.
 */
class MarketImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'asin,title,price,currency,list_price,rating,review_count,category,url,scraped_at';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-21 12:00:00');
        $this->deleteArtifacts();
    }

    protected function tearDown(): void
    {
        @unlink(base_path('tests/fixtures/market.test.csv'));
        @unlink(base_path('tests/fixtures/market.test.xlsx'));
        $this->deleteArtifacts();

        parent::tearDown();
    }

    private function deleteArtifacts(): void
    {
        File::delete(File::glob(storage_path('app/market/import-*.json')));
    }

    private function writeCsv(array $lines, bool $bom = false): string
    {
        $path = base_path('tests/fixtures/market.test.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, ($bom ? "\xEF\xBB\xBF" : '').implode("\r\n", $lines)."\r\n");

        return $path;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  first row = header; DateTime values become real date-styled cells
     */
    private function writeXlsx(array $rows): string
    {
        $path = base_path('tests/fixtures/market.test.xlsx');
        @mkdir(dirname($path), 0777, true);
        @unlink($path);

        $dateStyle = (new Style)->withFormat('yyyy-mm-dd hh:mm:ss');

        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(new Row(array_map(
                fn ($value) => Cell::fromValue($value, $value instanceof \DateTimeInterface ? $dateStyle : null),
                $row,
            )));
        }
        $writer->close();

        return $path;
    }

    /**
     * One CSV data row in self::HEADER column order; cells containing commas
     * are quoted like Power Automate's quote-all export would.
     */
    private function row(array $overrides = []): string
    {
        $cells = array_merge([
            'asin' => 'B0MARKET01',
            'title' => 'Anker 737 Power Bank',
            'price' => '99.99',
            'currency' => 'USD',
            'list_price' => '',
            'rating' => '4.5',
            'review_count' => '1234',
            'category' => 'Power Banks',
            'url' => 'https://www.amazon.com/dp/B0MARKET01',
            'scraped_at' => '',
        ], $overrides);

        return implode(',', array_map(
            fn ($value) => str_contains((string) $value, ',') ? '"'.$value.'"' : (string) $value,
            $cells,
        ));
    }

    /**
     * The latest run artifact (names are timestamped, so sort order is
     * chronological), decoded.
     */
    private function artifact(): array
    {
        $files = File::glob(storage_path('app/market/import-*.json'));
        sort($files);
        $this->assertNotEmpty($files, 'No report artifact was written.');

        $report = json_decode(File::get(end($files)), true);
        $this->assertIsArray($report);

        return $report;
    }

    /**
     * A curated product whose creation snapshot and check stamp are aged to
     * yesterday, so a same-day import registers as a fresh observation.
     */
    private function curatedProduct(array $attributes = []): Product
    {
        $product = Product::create(array_merge([
            'name' => 'Anker 737 Power Bank',
            'asin' => 'B0MARKET01',
            'affiliate_url' => 'https://www.amazon.com/dp/B0MARKET01',
            'price' => 99.99,
        ], $attributes));

        DB::table('product_price_snapshots')->where('product_id', $product->id)
            ->update(['created_at' => now()->subDay()]);
        DB::table('products')->where('id', $product->id)
            ->update(['price_checked_at' => now()->subDay()]);

        return $product->fresh();
    }

    public function test_imports_new_asins_with_snapshots_and_writes_a_stable_artifact(): void
    {
        $path = $this->writeCsv([
            self::HEADER,
            $this->row(),
            $this->row(['asin' => 'B0MARKET02', 'title' => 'Sony WH-1000XM5', 'price' => '278.00', 'category' => 'Headphones']),
            $this->row(['asin' => 'B0MARKET03', 'title' => 'Logitech MX Master 3S', 'price' => '89.00', 'category' => 'Mice']),
        ]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('Rows: 3 | Imported: 3 | Rejected: 0 | Duplicates: 0')
            ->expectsOutputToContain('ASINs: 3 new, 0 known (3 tracked)')
            ->assertSuccessful();

        $this->assertSame(3, MarketProduct::count());
        $this->assertSame(3, MarketPriceSnapshot::count());

        $product = MarketProduct::where('asin', 'B0MARKET01')->first();
        $this->assertSame('Anker 737 Power Bank', $product->title);
        $this->assertEquals(99.99, (float) $product->current_price);
        $this->assertEquals(4.5, (float) $product->rating);
        $this->assertSame(1234, $product->review_count);
        $this->assertSame('Power Banks', $product->category);
        $this->assertSame('https://www.amazon.com/dp/B0MARKET01', $product->url);
        $this->assertTrue($product->first_seen_at->equalTo(now()));
        $this->assertTrue($product->last_seen_at->equalTo(now()));
        $this->assertEquals(99.99, (float) $product->snapshots()->first()->price);

        $report = $this->artifact();

        // Stable key order is the artifact's diffability contract.
        $this->assertSame(
            ['run_at', 'file', 'config', 'totals', 'asins', 'curated', 'movers', 'new_lows', 'coverage', 'rejects', 'warnings', 'ignored_columns'],
            array_keys($report),
        );
        $this->assertSame(['rows' => 3, 'imported' => 3, 'rejected' => 0, 'duplicates' => 0], $report['totals']);
        $this->assertSame(['new' => 3, 'known' => 0, 'total_tracked' => 3], $report['asins']);
        $this->assertSame(
            ['matched_asins' => 0, 'products_updated' => 0, 'price_changes' => 0, 'same_price_checks' => 0, 'stale_skipped' => 0],
            $report['curated'],
        );
        $this->assertSame('market.test.csv', $report['file']);
        $this->assertSame(3, $report['coverage']['seen_this_run']);
        $this->assertSame(['Headphones' => 1, 'Mice' => 1, 'Power Banks' => 1], $report['coverage']['categories']);
        $this->assertSame([], $report['ignored_columns']);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $curated = $this->curatedProduct();
        $path = $this->writeCsv([self::HEADER, $this->row(['price' => '79.99'])]);

        $this->artisan('market:import', ['file' => $path, '--dry-run' => true])
            ->expectsOutputToContain('Rows: 1 | Imported: 1 | Rejected: 0 | Duplicates: 0')
            ->expectsOutputToContain('Dry run - nothing written.')
            ->assertSuccessful();

        $this->assertSame(0, MarketProduct::count());
        $this->assertSame(0, MarketPriceSnapshot::count());
        $this->assertEquals(99.99, (float) $curated->fresh()->price);
        $this->assertSame(1, $curated->priceSnapshots()->count());
        $this->assertSame([], File::glob(storage_path('app/market/import-*.json')));
    }

    public function test_missing_file_fails_with_guidance(): void
    {
        $this->artisan('market:import', ['file' => 'tests/fixtures/does-not-exist.csv'])
            ->expectsOutputToContain('File not found')
            ->assertFailed();
    }

    public function test_a_header_missing_a_required_column_aborts(): void
    {
        $path = $this->writeCsv(['asin,title,category', $this->row()]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('missing required column(s): price')
            ->assertFailed();

        $this->assertSame(0, MarketProduct::count());
        $this->assertSame([], File::glob(storage_path('app/market/import-*.json')));
    }

    public function test_bad_rows_are_rejected_with_reasons_and_the_run_continues(): void
    {
        $path = $this->writeCsv([
            self::HEADER,
            '', // blank line — must still count toward reject line numbers
            $this->row(['asin' => 'SHORT']),
            $this->row(['asin' => 'B0MARKET02', 'price' => 'See price in cart']),
            $this->row(['asin' => 'B0MARKET03', 'currency' => 'EUR']),
            $this->row(),
        ]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('Rows: 4 | Imported: 1 | Rejected: 3 | Duplicates: 0')
            ->assertSuccessful();

        $this->assertSame(1, MarketProduct::count());
        $this->assertSame('B0MARKET01', MarketProduct::first()->asin);

        $report = $this->artifact();
        $this->assertSame(3, $report['totals']['rejected']);
        $this->assertSame([3, 4, 5], array_column($report['rejects'], 'line'));
        $this->assertStringContainsString('asin', $report['rejects'][0]['reason']);
        $this->assertStringContainsString('price', $report['rejects'][1]['reason']);
        $this->assertStringContainsString('currency', $report['rejects'][2]['reason']);
    }

    public function test_bom_mixed_case_header_and_formatted_numbers_parse_clean(): void
    {
        $path = $this->writeCsv([
            'ASIN,Title,Price,Currency,List_Price,Rating,Review_Count,Category,Url,Scraped_At',
            $this->row(['price' => '$1,299.99', 'list_price' => '$1,499.00', 'review_count' => '12,345']),
        ], bom: true);

        $this->artisan('market:import', ['file' => $path])->assertSuccessful();

        $product = MarketProduct::where('asin', 'B0MARKET01')->first();
        $this->assertNotNull($product);
        $this->assertEquals(1299.99, (float) $product->current_price);
        $this->assertEquals(1499.00, (float) $product->list_price);
        $this->assertSame(12345, $product->review_count);
    }

    public function test_a_duplicate_asin_within_one_file_keeps_the_first_row(): void
    {
        $path = $this->writeCsv([
            self::HEADER,
            $this->row(['price' => '99.99']),
            $this->row(['price' => '89.99']),
        ]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('Rows: 2 | Imported: 1 | Rejected: 0 | Duplicates: 1')
            ->assertSuccessful();

        $this->assertSame(1, MarketProduct::count());
        $this->assertEquals(99.99, (float) MarketProduct::first()->current_price);
        $this->assertSame(1, MarketPriceSnapshot::count());
    }

    public function test_reimporting_the_same_price_only_advances_last_seen(): void
    {
        $path = $this->writeCsv([self::HEADER, $this->row()]);
        $this->artisan('market:import', ['file' => $path])->assertSuccessful();

        Carbon::setTestNow('2026-07-22 12:00:00');
        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('ASINs: 0 new, 1 known (1 tracked)')
            ->assertSuccessful();

        $product = MarketProduct::first();
        $this->assertSame(1, $product->snapshots()->count());
        $this->assertSame('2026-07-22', $product->last_seen_at->toDateString());
        $this->assertSame('2026-07-21', $product->first_seen_at->toDateString());
    }

    public function test_a_price_change_snapshots_and_reports_movers_and_new_lows(): void
    {
        $this->artisan('market:import', ['file' => $this->writeCsv([self::HEADER, $this->row(['price' => '100.00'])])])
            ->assertSuccessful();

        Carbon::setTestNow('2026-07-22 12:00:00');
        $this->artisan('market:import', ['file' => $this->writeCsv([self::HEADER, $this->row(['price' => '80.00'])])])
            ->expectsOutputToContain('Biggest mover: Anker 737 Power Bank -20.0% ($100.00 → $80.00) | New lows: 1')
            ->assertSuccessful();

        $product = MarketProduct::first();
        $this->assertSame(2, $product->snapshots()->count());
        $this->assertEquals(80.00, (float) $product->current_price);

        $report = $this->artifact();
        $this->assertSame(['total' => 1, 'listed' => [[
            'asin' => 'B0MARKET01',
            'title' => 'Anker 737 Power Bank',
            'previous' => 100.0,
            'current' => 80.0,
            'drop_pct' => 20.0,
        ]]], $report['movers']);
        $this->assertSame(['total' => 1, 'listed' => [[
            'asin' => 'B0MARKET01',
            'title' => 'Anker 737 Power Bank',
            'price' => 80.0,
            'previous_low' => 100.0,
        ]]], $report['new_lows']);
    }

    public function test_a_matching_curated_product_gets_the_price_with_source_import(): void
    {
        $curated = $this->curatedProduct();
        $path = $this->writeCsv([self::HEADER, $this->row(['price' => '79.99'])]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('Curated: 1 matched — 1 price changes, 0 same-price checks')
            ->assertSuccessful();

        $curated->refresh();
        $this->assertEquals(79.99, (float) $curated->price);
        $this->assertTrue($curated->price_checked_at->equalTo(now()));

        $this->assertSame(2, $curated->priceSnapshots()->count());
        $latest = $curated->priceSnapshots()->latest('id')->first();
        $this->assertSame('import', $latest->source);
        $this->assertEquals(79.99, (float) $latest->price);

        $this->assertSame('manual', ProductObserver::$source);

        $report = $this->artifact();
        $this->assertSame(
            ['matched_asins' => 1, 'products_updated' => 1, 'price_changes' => 1, 'same_price_checks' => 0, 'stale_skipped' => 0],
            $report['curated'],
        );
    }

    public function test_a_same_price_match_records_one_confirmed_check_per_day(): void
    {
        $curated = $this->curatedProduct();
        $path = $this->writeCsv([self::HEADER, $this->row(['price' => '99.99'])]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('Curated: 1 matched — 0 price changes, 1 same-price checks')
            ->assertSuccessful();

        $this->assertSame(2, $curated->priceSnapshots()->count());
        $latest = $curated->priceSnapshots()->latest('id')->first();
        $this->assertSame('import', $latest->source);
        $this->assertEquals(99.99, (float) $latest->price);

        // A second import the same day stays capped at one check snapshot.
        $this->artisan('market:import', ['file' => $path])->assertSuccessful();
        $this->assertSame(2, $curated->priceSnapshots()->count());
    }

    public function test_every_curated_product_sharing_the_asin_is_updated(): void
    {
        $first = $this->curatedProduct(['name' => 'Anker 737 (review A)']);
        $second = $this->curatedProduct(['name' => 'Anker 737 (review B)']);

        $this->artisan('market:import', ['file' => $this->writeCsv([self::HEADER, $this->row(['price' => '79.99'])])])
            ->assertSuccessful();

        $this->assertEquals(79.99, (float) $first->fresh()->price);
        $this->assertEquals(79.99, (float) $second->fresh()->price);

        $report = $this->artifact();
        $this->assertSame(1, $report['curated']['matched_asins']);
        $this->assertSame(2, $report['curated']['products_updated']);
        $this->assertSame(2, $report['curated']['price_changes']);
    }

    public function test_unparseable_optional_fields_are_nulled_and_counted(): void
    {
        $path = $this->writeCsv([
            self::HEADER,
            $this->row([
                'list_price' => 'N/A',
                'rating' => 'five stars',
                'review_count' => 'many',
                'url' => 'not-a-url',
                'scraped_at' => 'yesterday',
            ]),
        ]);

        $this->artisan('market:import', ['file' => $path])->assertSuccessful();

        $product = MarketProduct::first();
        $this->assertNotNull($product);
        $this->assertNull($product->list_price);
        $this->assertNull($product->rating);
        $this->assertNull($product->review_count);
        $this->assertNull($product->url);
        // Unparseable scraped_at falls back to import time.
        $this->assertTrue($product->first_seen_at->equalTo(now()));

        $report = $this->artifact();
        $this->assertSame(
            ['list_price' => 1, 'rating' => 1, 'review_count' => 1, 'url' => 1, 'scraped_at' => 1],
            $report['warnings'],
        );
    }

    public function test_brand_is_stored_and_not_erased_by_a_brandless_reimport(): void
    {
        $this->artisan('market:import', ['file' => $this->writeCsv([
            'asin,title,price,brand',
            'B0MARKET01,Anker 737 Power Bank,99.99,Anker',
        ])])->assertSuccessful();

        $this->assertSame('Anker', MarketProduct::first()->brand);

        Carbon::setTestNow('2026-07-22 12:00:00');
        $this->artisan('market:import', ['file' => $this->writeCsv([
            'asin,title,price',
            'B0MARKET01,Anker 737 Power Bank,89.99',
        ])])->assertSuccessful();

        $product = MarketProduct::first();
        $this->assertSame('Anker', $product->brand);
        $this->assertEquals(89.99, (float) $product->current_price);
    }

    public function test_a_combined_title_block_splits_into_title_and_description(): void
    {
        $this->artisan('market:import', ['file' => $this->writeCsv([
            self::HEADER,
            $this->row(['title' => 'Oura Ring 5 Sizing Kit - Size Before You Buy Oura Ring 5 - Unique Sizing, Not Standard Ring Sizing - Receive Amazon Credit for Oura Ring 5 Purchase']),
            $this->row(['asin' => 'B0MARKET02', 'title' => 'Amazon Fire TV Stick HD (newest model), free & live TV, Alexa Voice Remote']),
            $this->row(['asin' => 'B0MARKET03', 'title' => 'Sony WH-1000XM5 Wireless Noise-Canceling Headphones']),
        ])])->assertSuccessful();

        $oura = MarketProduct::where('asin', 'B0MARKET01')->first();
        $this->assertSame('Oura Ring 5 Sizing Kit', $oura->title);
        $this->assertSame('Size Before You Buy Oura Ring 5', $oura->description);

        // No dash boundary: falls back to the first ", ".
        $fireTv = MarketProduct::where('asin', 'B0MARKET02')->first();
        $this->assertSame('Amazon Fire TV Stick HD (newest model)', $fireTv->title);
        $this->assertSame('free & live TV', $fireTv->description);

        // Hyphenated words are not boundaries; no delimiter → no description.
        $sony = MarketProduct::where('asin', 'B0MARKET03')->first();
        $this->assertSame('Sony WH-1000XM5 Wireless Noise-Canceling Headphones', $sony->title);
        $this->assertNull($sony->description);

        // A later plain-title sighting must not erase the description.
        Carbon::setTestNow('2026-07-22 12:00:00');
        $this->artisan('market:import', ['file' => $this->writeCsv([
            self::HEADER,
            $this->row(['title' => 'Oura Ring 5 Sizing Kit', 'price' => '89.99']),
        ])])->assertSuccessful();

        $oura->refresh();
        $this->assertSame('Oura Ring 5 Sizing Kit', $oura->title);
        $this->assertSame('Size Before You Buy Oura Ring 5', $oura->description);
        $this->assertEquals(89.99, (float) $oura->current_price);
    }

    public function test_an_explicit_description_column_skips_the_split(): void
    {
        $this->artisan('market:import', ['file' => $this->writeCsv([
            'asin,title,price,description',
            'B0MARKET01,Oura Ring 5 Sizing Kit - Gen 3,10.00,Hand-written description',
        ])])->assertSuccessful();

        $product = MarketProduct::first();
        $this->assertSame('Oura Ring 5 Sizing Kit - Gen 3', $product->title);
        $this->assertSame('Hand-written description', $product->description);
    }

    public function test_unknown_header_columns_are_ignored_and_reported_once(): void
    {
        $path = $this->writeCsv([
            'asin,title,price,sponsored_flag,sponsored_flag',
            'B0MARKET01,Anker 737 Power Bank,99.99,1,1',
        ]);

        $this->artisan('market:import', ['file' => $path])->assertSuccessful();

        $this->assertSame(1, MarketProduct::count());
        $this->assertSame(['sponsored_flag'], $this->artifact()['ignored_columns']);
    }

    public function test_an_empty_file_aborts(): void
    {
        $path = base_path('tests/fixtures/market.test.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, '');

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('no header row')
            ->assertFailed();

        $this->assertSame([], File::glob(storage_path('app/market/import-*.json')));
    }

    public function test_an_xlsx_workbook_imports_with_native_date_cells(): void
    {
        $path = $this->writeXlsx([
            ['asin', 'title', 'price', 'rating', 'review_count', 'scraped_at'],
            ['B0MARKET01', 'Oura Ring 5 Sizing Kit - Size Before You Buy Oura Ring 5 - Unique Sizing', 10.0, 4.6, 338, new \DateTimeImmutable('2026-07-20 18:40:00')],
            ['B0MARKET02', 'Amazon Echo Dot (newest model)', 49.99, 4.7, 196178, new \DateTimeImmutable('2026-07-20 18:41:00')],
            // A datetime cell written without a date style arrives as a bare
            // Excel serial: 46224.25 = 2026-07-21 06:00:00.
            ['B0MARKET03', 'Sony WH-1000XM5', 278.00, 4.5, 12000, 46224.25],
        ]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('Rows: 3 | Imported: 3 | Rejected: 0 | Duplicates: 0')
            ->assertSuccessful();

        $oura = MarketProduct::where('asin', 'B0MARKET01')->first();
        $this->assertSame('Oura Ring 5 Sizing Kit', $oura->title);
        $this->assertSame('Size Before You Buy Oura Ring 5', $oura->description);
        $this->assertEquals(10.0, (float) $oura->current_price);
        $this->assertEquals(4.6, (float) $oura->rating);
        $this->assertSame(338, $oura->review_count);
        // A real Excel datetime cell lands as scrape time, not import time.
        $this->assertSame('2026-07-20 18:40:00', $oura->first_seen_at->format('Y-m-d H:i:s'));

        $this->assertSame(196178, MarketProduct::where('asin', 'B0MARKET02')->first()->review_count);
        $this->assertSame(
            '2026-07-21 06:00:00',
            MarketProduct::where('asin', 'B0MARKET03')->first()->first_seen_at->format('Y-m-d H:i:s'),
        );
        $this->assertSame(0, $this->artifact()['warnings']['scraped_at']);
    }

    public function test_a_stale_scrape_never_regresses_a_fresher_curated_price(): void
    {
        $curated = $this->curatedProduct(); // checked yesterday (2026-07-20)
        $path = $this->writeCsv([
            self::HEADER,
            $this->row(['price' => '49.99', 'scraped_at' => '2026-07-19 12:00:00']),
        ]);

        $this->artisan('market:import', ['file' => $path])
            ->expectsOutputToContain('1 stale skipped')
            ->assertSuccessful();

        $curated->refresh();
        $this->assertEquals(99.99, (float) $curated->price);
        $this->assertSame('2026-07-20', $curated->price_checked_at->toDateString());
        $this->assertSame(1, $curated->priceSnapshots()->count());

        // The market layer still records the observation, at scrape time.
        $market = MarketProduct::where('asin', 'B0MARKET01')->first();
        $this->assertNotNull($market);
        $this->assertSame('2026-07-19', $market->first_seen_at->toDateString());
        $this->assertSame('2026-07-19', $market->snapshots()->first()->created_at->toDateString());

        $report = $this->artifact();
        $this->assertSame(1, $report['curated']['stale_skipped']);
        $this->assertSame(0, $report['curated']['products_updated']);
    }
}
