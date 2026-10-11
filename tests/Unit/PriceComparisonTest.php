<?php

namespace Tests\Unit;

use App\Support\PriceComparison;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Plan 11 Phase 11.1: the filters, the ranking, and the freshness gate.
 *
 * Pure PHPUnit, no Laravel boot (mirrors ArticleBodyTest / BuyOrWaitTest): if
 * this file ever needs the container, PriceComparison has grown a facade
 * dependency it is not supposed to have.
 */
class PriceComparisonTest extends TestCase
{
    /** Written as an escape so this file holds no literal em dash either. */
    private const EM_DASH = "\u{2014}";

    /** A deliberately short stand-in for config('price-compare.retailers'). */
    private const HOSTS = [
        'walmart.com', 'bestbuy.com', 'target.com',
        'newegg.com', 'costco.com', 'adorama.com', 'staples.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- filters

    public function test_it_drops_rows_the_model_did_not_confirm_as_an_exact_model_match(): void
    {
        $result = $this->normalize([
            $this->row(['retailer' => 'Walmart', 'exact_model_match' => true]),
            $this->row(['retailer' => 'Best Buy', 'url' => 'https://bestbuy.com/x', 'exact_model_match' => false]),
            // Absent is not the same as true: an unstated match is not a match.
            $this->row(['retailer' => 'Target', 'url' => 'https://target.com/x', 'exact_model_match' => null]),
        ]);

        $this->assertSame(['Walmart'], array_column($result['rows'], 'retailer'));
    }

    public function test_it_drops_a_host_outside_the_whitelist_even_though_the_model_returned_it(): void
    {
        // Defence in depth: allowed_domains already scoped the search, so this
        // row should not exist. If it does, it is a bug, not a result.
        $result = $this->normalize([
            $this->row(['retailer' => 'Walmart']),
            $this->row(['retailer' => 'Amazon', 'price' => 9.99, 'url' => 'https://www.amazon.com/dp/B000']),
            $this->row(['retailer' => 'Some Tracker', 'price' => 1.00, 'url' => 'https://camelcamelcamel.com/x']),
        ]);

        $this->assertSame(['Walmart'], array_column($result['rows'], 'retailer'));
    }

    public function test_a_lookalike_host_does_not_satisfy_the_whitelist(): void
    {
        $result = $this->normalize([
            $this->row(['url' => 'https://walmart.com.deals.example.net/x']),
            $this->row(['url' => 'https://notwalmart.com/x']),
        ]);

        $this->assertSame([], $result['rows']);
        $this->assertTrue($result['is_empty']);
    }

    public function test_bare_www_and_subdomain_hosts_all_match_the_whitelist(): void
    {
        $result = $this->normalize([
            $this->row(['retailer' => 'Walmart', 'price' => 10.00, 'url' => 'https://walmart.com/a']),
            $this->row(['retailer' => 'Best Buy', 'price' => 11.00, 'url' => 'https://www.bestbuy.com/b']),
            $this->row(['retailer' => 'Target', 'price' => 12.00, 'url' => 'https://shop.target.com/c']),
        ]);

        $this->assertSame(['Walmart', 'Best Buy', 'Target'], array_column($result['rows'], 'retailer'));
    }

    /**
     * The whitelist bypass found in review. parse_url() follows RFC 3986, where
     * a backslash is ordinary userinfo, so it reports host "walmart.com" for
     * `https://amazon.com\@walmart.com/...`. Every browser uses the WHATWG
     * parser, which treats the backslash as a delimiter and navigates to
     * amazon.com. A row that renders as "Walmart" and links to Amazon is
     * exactly the §2(b) breach the whitelist exists to prevent.
     *
     * @param  string  $url
     */
    #[DataProvider('hostileAuthorities')]
    public function test_a_url_whose_host_php_and_browsers_disagree_about_is_rejected(string $url): void
    {
        $result = $this->normalize([$this->row(['url' => $url])]);

        $this->assertSame([], $result['rows'], "A browser does not read the host of {$url} the way PHP does.");
    }

    public static function hostileAuthorities(): array
    {
        return [
            'backslash hides amazon in userinfo' => ['https://amazon.com\@walmart.com/dp/B000'],
            'backslash hides any host' => ['https://evil.com\@walmart.com/p'],
            'plain userinfo' => ['https://walmart.com@evil.com/'],
            'tab in authority' => ["https://walmart.com	.evil.com/"],
            'newline in authority' => ["https://walmart
.com/x"],
            'space in url' => ['https://walmart.com/a b'],
            'angle bracket' => ['https://walmart.com/<script>'],
        ];
    }

    public function test_an_over_long_url_is_rejected(): void
    {
        $result = $this->normalize([$this->row(['url' => 'https://walmart.com/'.str_repeat('a', 2100)])]);

        $this->assertSame([], $result['rows']);
    }

    public function test_a_data_url_is_not_a_source(): void
    {
        $result = $this->normalize([$this->row(['url' => 'data:text/html,<h1>hi</h1>'])]);

        $this->assertSame([], $result['rows']);
    }

    public function test_a_non_http_url_is_not_a_source(): void
    {
        $result = $this->normalize([
            $this->row(['url' => 'javascript:alert(1)']),
            $this->row(['url' => 'ftp://walmart.com/x']),
            $this->row(['url' => '']),
        ]);

        $this->assertSame([], $result['rows']);
    }

    #[DataProvider('unusablePrices')]
    public function test_it_drops_unusable_prices(mixed $price): void
    {
        $result = $this->normalize([$this->row(['price' => $price])], ourPrice: 199.99);

        $this->assertSame([], $result['rows'], 'A row priced '.var_export($price, true).' should not survive.');
    }

    public static function unusablePrices(): array
    {
        return [
            'null' => [null],
            'zero' => [0],
            'negative' => [-19.99],
            'not numeric' => ['see site'],
            // 100x our own price is a mis-parsed "$1,899" on a $19 item.
            'absurd magnitude' => [25000.00],
        ];
    }

    public function test_a_numeric_string_price_is_accepted_and_rounded(): void
    {
        $result = $this->normalize([$this->row(['price' => '189.006'])]);

        $this->assertSame(189.01, $result['rows'][0]['price']);
    }

    public function test_duplicate_retailers_collapse_to_the_single_lowest_price(): void
    {
        $result = $this->normalize([
            $this->row(['retailer' => 'Walmart', 'price' => 199.00, 'url' => 'https://www.walmart.com/ip/a']),
            $this->row(['retailer' => 'Walmart.com', 'price' => 179.00, 'url' => 'https://walmart.com/ip/b']),
            $this->row(['retailer' => 'Walmart', 'price' => 189.00, 'url' => 'https://shop.walmart.com/ip/c']),
        ]);

        $this->assertCount(1, $result['rows']);
        $this->assertSame(179.00, $result['rows'][0]['price']);
        $this->assertSame('https://walmart.com/ip/b', $result['rows'][0]['url']);
    }

    public function test_rows_sort_ascending_and_cap_at_six(): void
    {
        $prices = [70.0, 30.0, 50.0, 10.0, 60.0, 20.0, 40.0];
        $rows = [];

        foreach (self::HOSTS as $i => $host) {
            $rows[] = $this->row([
                'retailer' => $host,
                'price' => $prices[$i],
                'url' => "https://{$host}/x",
            ]);
        }

        $result = $this->normalize($rows, ourPrice: 100.00);

        $this->assertCount(PriceComparison::MAX_ROWS, $result['rows']);
        $this->assertSame([10.0, 20.0, 30.0, 40.0, 50.0, 60.0], array_column($result['rows'], 'price'));
    }

    // ---------------------------------------------------------------- verdict

    public function test_a_fresh_price_beaten_by_a_retailer_names_that_retailer_and_the_difference(): void
    {
        $result = $this->normalize(
            [$this->row(['retailer' => 'Walmart', 'price' => 189.99])],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDays(2),
        );

        $this->assertTrue($result['is_fresh']);
        $this->assertSame('retailer', $result['winner']);
        $this->assertStringContainsString('Walmart', $result['winner_label']);
        $this->assertStringContainsString('$189.99', $result['winner_label']);
        $this->assertStringContainsString('$10.00', $result['winner_label']);
        $this->assertStringNotContainsString(self::EM_DASH, $result['winner_label']);
    }

    public function test_a_fresh_price_lower_than_every_retailer_credits_amazon(): void
    {
        $result = $this->normalize(
            [$this->row(['retailer' => 'Walmart', 'price' => 209.99])],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDays(2),
        );

        $this->assertSame('amazon', $result['winner']);
        $this->assertStringContainsString('$199.99', $result['winner_label']);
        $this->assertStringContainsString('$10.00', $result['winner_label']);
    }

    public function test_an_exact_tie_goes_to_amazon(): void
    {
        $result = $this->normalize(
            [$this->row(['retailer' => 'Walmart', 'price' => 199.99])],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDays(2),
        );

        $this->assertSame('amazon', $result['winner']);
        $this->assertStringContainsString('matches', $result['winner_label']);
    }

    /**
     * THE BIAS GUARD. Competitor prices are live, ours is whatever we last
     * recorded. Past the freshness window we still show both, but we refuse to
     * declare a winner, because the error that matters is wrongly crowning
     * AMAZON: the one that flatters our own commission.
     */
    public function test_a_stale_price_suppresses_the_winner_while_the_rows_still_render(): void
    {
        $result = $this->normalize(
            [$this->row(['retailer' => 'Walmart', 'price' => 149.99])],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDays(30),
        );

        $this->assertFalse($result['is_fresh']);
        $this->assertNull($result['winner']);
        $this->assertNull($result['winner_label']);
        $this->assertCount(1, $result['rows'], 'The comparison itself is never gated, only the claim.');
    }

    public function test_a_never_checked_price_is_not_fresh(): void
    {
        $result = PriceComparison::normalize(
            $this->payload([$this->row(['price' => 149.99])]),
            199.99,
            null,
            self::HOSTS,
            now: Carbon::now(),
        );

        $this->assertFalse($result['is_fresh']);
        $this->assertNull($result['winner']);
    }

    public function test_no_tracked_price_of_our_own_means_no_winner_but_still_a_table(): void
    {
        $result = $this->normalize(
            [$this->row(['retailer' => 'Walmart', 'price' => 149.99])],
            ourPrice: null,
            checkedAt: Carbon::now()->subDay(),
        );

        $this->assertNull($result['our_price']);
        $this->assertNull($result['winner']);
        $this->assertCount(1, $result['rows']);
    }

    /**
     * An unbuyable listing is still worth showing, but calling it "currently
     * the lowest" would be a claim the reader cannot act on.
     */
    public function test_an_out_of_stock_row_renders_but_never_wins(): void
    {
        $result = $this->normalize(
            [
                $this->row(['retailer' => 'Walmart', 'price' => 149.99, 'in_stock' => false]),
                $this->row(['retailer' => 'Best Buy', 'price' => 209.99, 'url' => 'https://bestbuy.com/x']),
            ],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDay(),
        );

        $this->assertCount(2, $result['rows']);
        $this->assertFalse($result['rows'][0]['in_stock']);
        $this->assertSame('amazon', $result['winner']);
        $this->assertStringContainsString('Best Buy', $result['winner_label']);
        $this->assertStringNotContainsString('Walmart', $result['winner_label']);
        // Both winner branches must say "in-stock", or a reader seeing a
        // cheaper sold-out row above reads a flat contradiction.
        $this->assertStringContainsString('in-stock', $result['winner_label']);
    }

    public function test_nothing_in_stock_anywhere_means_no_verdict(): void
    {
        $result = $this->normalize(
            [$this->row(['price' => 149.99, 'in_stock' => false])],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDay(),
        );

        $this->assertCount(1, $result['rows']);
        $this->assertNull($result['winner']);
    }

    public function test_a_row_without_a_stock_field_is_assumed_to_be_in_stock(): void
    {
        $row = $this->row(['price' => 149.99]);
        unset($row['in_stock']);

        $result = $this->normalize([$row], ourPrice: 199.99, checkedAt: Carbon::now()->subDay());

        $this->assertTrue($result['rows'][0]['in_stock']);
        $this->assertSame('retailer', $result['winner']);
    }

    /**
     * A mis-read financing figure ("$18.99/mo" on a $199 item) would otherwise
     * produce a confident, badly wrong claim. The row still renders; only the
     * verdict is withheld.
     */
    public function test_an_implausibly_cheap_row_renders_but_never_wins(): void
    {
        $result = $this->normalize(
            [
                $this->row(['retailer' => 'Walmart', 'price' => 18.99]),
                $this->row(['retailer' => 'Best Buy', 'price' => 209.99, 'url' => 'https://bestbuy.com/x']),
            ],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDay(),
        );

        $this->assertCount(2, $result['rows'], 'The row is shown: we just will not build a claim on it.');
        $this->assertSame('amazon', $result['winner']);
        $this->assertStringNotContainsString('Walmart', $result['winner_label']);
    }

    public function test_a_steep_but_plausible_discount_still_wins(): void
    {
        $result = $this->normalize(
            [$this->row(['retailer' => 'Walmart', 'price' => 99.99])],
            ourPrice: 199.99,
            checkedAt: Carbon::now()->subDay(),
        );

        $this->assertSame('retailer', $result['winner']);
    }

    // ----------------------------------------------------------------- states

    public function test_zero_surviving_rows_is_an_empty_result_and_not_a_suppressed_one(): void
    {
        $result = $this->normalize([]);

        $this->assertTrue($result['is_empty']);
        $this->assertFalse($result['suppressed']);
        $this->assertSame([], $result['rows']);
    }

    public function test_confidence_below_the_floor_suppresses_the_whole_widget(): void
    {
        $result = $this->normalize(
            [$this->row(['price' => 149.99])],
            payloadOverrides: ['confidence' => 0.4],
        );

        $this->assertTrue($result['suppressed']);
        $this->assertFalse($result['is_empty'], 'suppressed and empty are different outcomes and render differently.');
        $this->assertSame([], $result['rows'], 'A payload we do not trust must not leak rows into the view.');
    }

    public function test_a_payload_with_no_confidence_at_all_fails_closed(): void
    {
        $payload = $this->payload([$this->row()]);
        unset($payload['confidence']);

        $result = PriceComparison::normalize($payload, 199.99, Carbon::now(), self::HOSTS, now: Carbon::now());

        $this->assertTrue($result['suppressed']);
    }

    public function test_a_confidence_exactly_on_the_floor_is_trusted(): void
    {
        $result = $this->normalize(
            [$this->row(['price' => 149.99])],
            payloadOverrides: ['confidence' => 0.6],
        );

        $this->assertFalse($result['suppressed']);
    }

    public function test_a_malformed_results_key_is_an_empty_result_not_a_crash(): void
    {
        $result = $this->normalize([], payloadOverrides: ['results' => 'nope']);

        $this->assertTrue($result['is_empty']);
    }

    // ------------------------------------------------------------ provenance

    public function test_the_checked_count_is_clamped_to_the_whitelist_we_actually_searched(): void
    {
        $inflated = $this->normalize([], payloadOverrides: ['retailers_checked' => 400]);
        $honest = $this->normalize([], payloadOverrides: ['retailers_checked' => 3]);
        $missing = $this->normalize([], payloadOverrides: ['retailers_checked' => null]);

        $this->assertSame(count(self::HOSTS), $inflated['retailers_checked']);
        $this->assertSame(3, $honest['retailers_checked']);
        // Falls to 0, never to the ceiling: a malformed payload must not assert
        // our largest claim on our weakest data.
        $this->assertSame(0, $missing['retailers_checked']);
    }

    public function test_our_own_price_and_stamp_pass_through_untouched(): void
    {
        $checkedAt = Carbon::now()->subDays(3);

        $result = $this->normalize([$this->row()], ourPrice: 199.99, checkedAt: $checkedAt);

        $this->assertSame(199.99, $result['our_price']);
        $this->assertTrue($checkedAt->equalTo($result['our_checked_at']));
    }

    public function test_a_hostile_retailer_name_is_flattened_and_capped(): void
    {
        $result = $this->normalize([$this->row(['retailer' => "  Wal\nmart  ".str_repeat('x', 200)])]);

        $name = $result['rows'][0]['retailer'];

        $this->assertSame(60, mb_strlen($name));
        $this->assertStringNotContainsString("\n", $name);
        $this->assertStringStartsWith('Wal mart x', $name);
    }

    public function test_an_unusable_retailer_name_falls_back_to_the_matched_domain(): void
    {
        $result = $this->normalize([$this->row(['retailer' => '   '])]);

        $this->assertSame('walmart.com', $result['rows'][0]['retailer']);
    }

    public function test_the_whitelist_is_normalised_before_it_is_matched_against(): void
    {
        $result = PriceComparison::normalize(
            $this->payload([$this->row(['url' => 'https://www.walmart.com/ip/1'])]),
            199.99,
            Carbon::now(),
            ['  WWW.Walmart.COM.  '],
            now: Carbon::now(),
        );

        $this->assertCount(1, $result['rows'], 'A stray www. or capital in config must not silently disable a retailer.');
        $this->assertSame(1, $result['retailers_checked']);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $payloadOverrides
     * @return array<string, mixed>
     */
    private function normalize(
        array $results,
        ?float $ourPrice = 199.99,
        ?CarbonInterface $checkedAt = null,
        array $payloadOverrides = [],
    ): array {
        return PriceComparison::normalize(
            $this->payload($results, $payloadOverrides),
            $ourPrice,
            $checkedAt ?? Carbon::now()->subDay(),
            self::HOSTS,
            7,
            0.6,
            Carbon::now(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $results, array $overrides = []): array
    {
        return array_merge([
            'confidence' => 0.9,
            'retailers_checked' => count(self::HOSTS),
            'results' => $results,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'retailer' => 'Walmart',
            'price' => 189.00,
            'url' => 'https://www.walmart.com/ip/123',
            'in_stock' => true,
            'exact_model_match' => true,
        ], $overrides);
    }
}
