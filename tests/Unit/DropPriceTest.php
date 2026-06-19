<?php

namespace Tests\Unit;

use App\Support\DropPrice;
use PHPUnit\Framework\TestCase;

class DropPriceTest extends TestCase
{
    public function test_exact_guess_wins_and_nails_it(): void
    {
        $result = DropPrice::evaluate(200, 200);

        $this->assertTrue($result['won']);
        $this->assertSame('nailed', $result['band']);
        $this->assertSame('equal', $result['direction']);
    }

    public function test_low_guess_points_higher_and_high_guess_points_lower(): void
    {
        // Answer is above the guess → tell the player to go higher.
        $this->assertSame('higher', DropPrice::evaluate(150, 200)['direction']);
        // Answer is below the guess → tell the player to go lower.
        $this->assertSame('lower', DropPrice::evaluate(250, 200)['direction']);
    }

    public function test_band_boundaries_are_inclusive_at_warm(): void
    {
        // 9% off (inside the warm threshold) → hot.
        $this->assertSame('hot', DropPrice::evaluate(182, 200)['band']);   // 18/200 = 0.09
        // Exactly 10% off → warm (the boundary is inclusive).
        $this->assertSame('warm', DropPrice::evaluate(180, 200)['band']);  // 20/200 = 0.10
        // Exactly 25% off → still warm (freezing is strictly greater than 25%).
        $this->assertSame('warm', DropPrice::evaluate(150, 200)['band']);  // 50/200 = 0.25
        // 26% off → freezing.
        $this->assertSame('freezing', DropPrice::evaluate(148, 200)['band']); // 52/200 = 0.26
    }

    public function test_bands_are_symmetric_for_over_and_under_guesses(): void
    {
        // A guess the same distance above vs below the answer lands in the same band.
        $this->assertSame('hot', DropPrice::evaluate(190, 200)['band']);
        $this->assertSame('hot', DropPrice::evaluate(210, 200)['band']);

        $this->assertSame('freezing', DropPrice::evaluate(50, 200)['band']);
        $this->assertSame('freezing', DropPrice::evaluate(350, 200)['band']);
    }

    public function test_non_winning_results_are_never_flagged_won(): void
    {
        foreach ([1, 180, 199, 201, 500] as $guess) {
            $this->assertFalse(DropPrice::evaluate($guess, 200)['won']);
            $this->assertNotSame('nailed', DropPrice::evaluate($guess, 200)['band']);
        }
    }

    public function test_result_exposes_only_ordinal_keys_never_a_distance(): void
    {
        $result = DropPrice::evaluate(150, 200);

        // The shape that ships to the browser must not carry the gap/percentage.
        $this->assertSame(['direction', 'band', 'won'], array_keys($result));
        $this->assertArrayNotHasKey('pct', $result);
        $this->assertArrayNotHasKey('distance', $result);
    }
}
