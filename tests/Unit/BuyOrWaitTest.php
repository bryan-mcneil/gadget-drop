<?php

namespace Tests\Unit;

use App\Models\ReleaseCycle;
use App\Support\BuyOrWait;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Plan 06 §Phase 6.2: every cell of the decision matrix, the confidence rules,
 * and the sentence's honesty properties.
 *
 * Pure PHPUnit, no Laravel boot (mirrors ArticleBodyTest): if this file ever
 * needs the container, BuyOrWait has grown a hard facade dependency it is not
 * supposed to have.
 */
class BuyOrWaitTest extends TestCase
{
    /** Written as an escape so this file holds no literal em dash either. */
    private const EM_DASH = "\u{2014}";

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A cycle sitting at roughly `$position` through its cadence. */
    private function cycle(float $position, int $cadence = 12, array $overrides = []): ReleaseCycle
    {
        $release = Carbon::now()->subDays((int) round($position * $cadence * 30.4375));

        return new ReleaseCycle(array_merge([
            'name' => 'Test Line',
            'slug' => 'test-line',
            'typical_month' => 9,
            'cadence_months' => $cadence,
            'last_release_name' => 'Test Line 3',
            'last_release_at' => $release->toDateString(),
            'next_expected_note' => 'Shipped every year since 2019.',
            'source_url' => 'https://example.com/press-release',
            'verified_at' => Carbon::now()->subMonth()->toDateString(),
        ], $overrides));
    }

    /** A PriceIntel::stats()-shaped array in the given verdict tier. */
    private function stats(string $tier, float $current = 90.0, float $avg = 100.0): array
    {
        return [
            'current' => $current,
            'checked_at' => Carbon::now()->subDay()->toIso8601String(),
            'tracking_since' => Carbon::now()->subDays(90)->toIso8601String(),
            'has_stats' => true,
            'low90' => 85.0,
            'high90' => 120.0,
            'avg90' => $avg,
            'low30' => 85.0,
            'verdict' => $tier,
            'drop_pct' => 10.0,
            'points' => [],
        ];
    }

    /** Priced, but still behind PriceIntel's honesty gate. */
    private function gatedStats(): array
    {
        return [
            'current' => 90.0,
            'checked_at' => Carbon::now()->subDay()->toIso8601String(),
            'tracking_since' => null,
            'has_stats' => false,
            'low90' => null,
            'high90' => null,
            'avg90' => null,
            'verdict' => null,
            'drop_pct' => null,
            'points' => [],
        ];
    }

    // ---------------------------------------------------------------- matrix

    public function test_late_cycle_waits_for_the_refresh_whatever_the_price_says(): void
    {
        foreach (['good', 'typical', 'elevated'] as $tier) {
            $result = BuyOrWait::verdict($this->cycle(0.9), $this->stats($tier));

            $this->assertSame(BuyOrWait::WAIT_FOR_REFRESH, $result['verdict'], "tier {$tier}");
        }
    }

    public function test_late_cycle_at_a_record_low_becomes_a_defensible_either(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.9), $this->stats('lowest'));

        $this->assertSame(BuyOrWait::EITHER, $result['verdict']);
        $this->assertStringContainsString('clearance', $result['sentence']);
    }

    public function test_fresh_cycle_with_a_good_or_record_price_says_buy(): void
    {
        foreach (['good', 'lowest'] as $tier) {
            $result = BuyOrWait::verdict($this->cycle(0.2), $this->stats($tier));

            $this->assertSame(BuyOrWait::BUY, $result['verdict'], "tier {$tier}");
            $this->assertSame('high', $result['confidence'], "tier {$tier}");
        }
    }

    public function test_fresh_cycle_at_an_elevated_price_waits_for_the_price(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.2), $this->stats('elevated', 120.0));

        $this->assertSame(BuyOrWait::WAIT_FOR_PRICE, $result['verdict']);
        $this->assertStringContainsString('no refresh to wait for', $result['sentence']);
    }

    public function test_fresh_cycle_at_a_typical_price_has_no_strong_signal(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.2), $this->stats('typical', 100.0));

        $this->assertSame(BuyOrWait::EITHER, $result['verdict']);
        $this->assertStringContainsString('No strong signal', $result['sentence']);
        // An early-cycle "either" must not describe itself as mid-cycle.
        $this->assertStringContainsString('early in the cycle', $result['sentence']);
        $this->assertStringNotContainsString('mid-cycle', $result['sentence']);
    }

    public function test_a_mid_cycle_either_says_mid_cycle(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.5), $this->stats('typical', 100.0));

        $this->assertStringContainsString('mid-cycle', $result['sentence']);
    }

    public function test_generated_sentences_carry_no_em_dashes(): void
    {
        // This copy renders as editorial prose AND is quoted verbatim by agents,
        // so it follows the pipeline's style bans (CONTENT-GUIDELINES.md).
        foreach ([0.1, 0.5, 0.9, 1.5] as $position) {
            foreach ([null, 'lowest', 'good', 'typical', 'elevated'] as $tier) {
                $result = BuyOrWait::verdict(
                    $this->cycle($position, 12, ['slug' => "p{$position}-".($tier ?? 'none')]),
                    $tier ? $this->stats($tier) : null,
                );

                $this->assertStringNotContainsString(self::EM_DASH, $result['sentence'], "position {$position}, tier ".($tier ?? 'none'));

                foreach ($result['factors'] as $factor) {
                    $this->assertStringNotContainsString(self::EM_DASH, $factor['detail']);
                }
            }
        }
    }

    public function test_mid_cycle_is_either_regardless_of_the_price_tier(): void
    {
        foreach (['lowest', 'good', 'typical', 'elevated'] as $tier) {
            $result = BuyOrWait::verdict($this->cycle(0.5), $this->stats($tier));

            $this->assertSame(BuyOrWait::EITHER, $result['verdict'], "tier {$tier}");
        }
    }

    public function test_the_matrix_boundaries_are_where_the_docblock_says(): void
    {
        // Just inside "fresh" buys; just outside it does not.
        $this->assertSame(BuyOrWait::BUY, BuyOrWait::verdict($this->cycle(0.30), $this->stats('good'))['verdict']);
        $this->assertSame(BuyOrWait::EITHER, BuyOrWait::verdict($this->cycle(0.40), $this->stats('good'))['verdict']);
        // Just inside "late" waits; just outside it does not.
        $this->assertSame(BuyOrWait::WAIT_FOR_REFRESH, BuyOrWait::verdict($this->cycle(0.85), $this->stats('typical'))['verdict']);
        $this->assertSame(BuyOrWait::EITHER, BuyOrWait::verdict($this->cycle(0.75), $this->stats('typical'))['verdict']);
    }

    // ------------------------------------------------------------ confidence

    public function test_a_cycle_only_verdict_never_claims_high_confidence(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.9), null);

        $this->assertSame(BuyOrWait::WAIT_FOR_REFRESH, $result['verdict']);
        $this->assertSame('medium', $result['confidence']);
        $this->assertStringContainsString('release timing alone', $result['sentence']);
    }

    public function test_gated_price_stats_are_treated_as_no_price_data(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.2), $this->gatedStats());

        $this->assertSame(BuyOrWait::EITHER, $result['verdict']);
        $this->assertSame('low', $result['confidence']);
    }

    public function test_stale_cycle_data_can_never_produce_high_confidence(): void
    {
        $stale = $this->cycle(0.2, 12, ['verified_at' => Carbon::now()->subMonths(9)->toDateString()]);

        $result = BuyOrWait::verdict($stale, $this->stats('good'));

        $this->assertSame(BuyOrWait::BUY, $result['verdict']);
        $this->assertNotSame('high', $result['confidence']);
        $this->assertStringContainsString('re-checked', $result['sentence']);
        $this->assertStringContainsString('directional', $result['sentence']);
    }

    public function test_an_either_verdict_tops_out_at_medium_confidence(): void
    {
        $rich = BuyOrWait::verdict($this->cycle(0.5), $this->stats('typical'));
        $thin = BuyOrWait::verdict($this->cycle(0.5), null);

        $this->assertSame('medium', $rich['confidence']);
        $this->assertSame('low', $thin['confidence']);
    }

    // -------------------------------------------------------------- sentence

    public function test_the_sentence_is_dated_hedged_and_names_the_line(): void
    {
        $cycle = $this->cycle(0.9, 12, [
            'name' => 'iPhone',
            'last_release_name' => 'iPhone 17',
            'last_release_at' => '2025-09-19',
        ]);

        $sentence = BuyOrWait::verdict($cycle, $this->stats('typical', 100.0))['sentence'];

        // Hedging is a product feature: the sentence describes a pattern in
        // shipped history, it never promises an unannounced product.
        $this->assertStringContainsString('Historically', $sentence);
        $hedges = array_filter(
            ['typical', 'usually', 'about', 'tends to'],
            fn (string $word) => str_contains($sentence, $word),
        );
        $this->assertGreaterThanOrEqual(2, count($hedges), "not enough hedging in: {$sentence}");

        $this->assertStringContainsString('iPhone', $sentence);
        $this->assertStringContainsString('iPhone 17', $sentence);
        $this->assertStringContainsString('September 2025', $sentence);
        // Never a bare command.
        $this->assertStringNotContainsString('You must', $sentence);
    }

    public function test_an_overdue_line_gets_overdue_phrasing_not_percentages(): void
    {
        $result = BuyOrWait::verdict($this->cycle(1.85), $this->stats('typical'));

        $this->assertSame(BuyOrWait::WAIT_FOR_REFRESH, $result['verdict']);
        $this->assertGreaterThan(1.0, $result['cycle_position']);
        $this->assertStringContainsString('overdue', $result['sentence']);
        $this->assertStringContainsString('past the usual refresh window', $result['sentence']);
    }

    public function test_the_price_clause_frames_the_numbers_as_ours_and_dates_them(): void
    {
        $sentence = BuyOrWait::verdict($this->cycle(0.2), $this->stats('good', 90.0, 100.0))['sentence'];

        $this->assertStringContainsString('$90.00', $sentence);
        $this->assertStringContainsString('tracked 90-day average of $100.00', $sentence);
        $this->assertStringContainsString('as of Jul 21, 2026', $sentence);
    }

    // --------------------------------------------------------------- factors

    public function test_every_cycle_factor_carries_its_source_and_check_date(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.5), $this->stats('typical'));

        $labels = array_column($result['factors'], 'label');
        $this->assertSame(['Release cadence', 'Current model', 'Tracked price'], $labels);

        $this->assertSame('https://example.com/press-release', $result['factors'][0]['source_url']);
        $this->assertSame('2026-06-22', $result['factors'][0]['as_of']);
        $this->assertStringContainsString('Shipped every year since 2019.', $result['factors'][0]['detail']);
        $this->assertStringContainsString('September', $result['factors'][0]['detail']);

        $this->assertStringContainsString('not a list price', $result['factors'][2]['detail']);
        $this->assertSame('2026-07-21', $result['factors'][2]['as_of']);
    }

    public function test_the_price_factor_states_the_gate_rather_than_going_quiet(): void
    {
        $result = BuyOrWait::verdict($this->cycle(0.5), $this->gatedStats());

        $this->assertStringContainsString('Not weighed in', $result['factors'][2]['detail']);
        $this->assertStringContainsString('snapshots spanning', $result['factors'][2]['detail']);
        $this->assertNull($result['factors'][2]['as_of']);
    }

    public function test_labels_cover_every_verdict(): void
    {
        $this->assertSame('Buy now', BuyOrWait::label(BuyOrWait::BUY));
        $this->assertSame('Wait for the refresh', BuyOrWait::label(BuyOrWait::WAIT_FOR_REFRESH));
        $this->assertSame('Wait for a better price', BuyOrWait::label(BuyOrWait::WAIT_FOR_PRICE));
        $this->assertSame('No strong signal', BuyOrWait::label(BuyOrWait::EITHER));

        $this->assertSame('a refresh is close', BuyOrWait::shortLabel(BuyOrWait::WAIT_FOR_REFRESH));
        $this->assertSame('emerald', BuyOrWait::tone(BuyOrWait::BUY));
    }
}
