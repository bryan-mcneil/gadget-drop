<?php

namespace Tests\Unit;

use App\Support\SearchIntel;
use PHPUnit\Framework\TestCase;

/**
 * Pure math on SearchIntel — no Laravel boot (an explicit curve is threaded in
 * so expectedCtr never falls through to config/DB), mirroring ArticleBodyTest.
 */
class SearchIntelTest extends TestCase
{
    private const CURVE = [1 => 0.30, 2 => 0.15, 3 => 0.10, 8 => 0.024, 20 => 0.005];

    public function test_expected_ctr_reads_the_curve_by_rounded_position(): void
    {
        $this->assertSame(0.10, SearchIntel::expectedCtr(3.2, self::CURVE));
        $this->assertSame(0.024, SearchIntel::expectedCtr(8.0, self::CURVE));
    }

    public function test_expected_ctr_clamps_out_of_range_positions(): void
    {
        $this->assertSame(0.30, SearchIntel::expectedCtr(0.4, self::CURVE));  // clamps up to 1
        $this->assertSame(0.005, SearchIntel::expectedCtr(25.0, self::CURVE)); // clamps down to 20
    }

    public function test_score_is_impressions_times_ctr_headroom(): void
    {
        $this->assertSame(5.0, SearchIntel::score(100, 0.10, 0.05));
    }

    public function test_score_never_goes_negative(): void
    {
        $this->assertSame(0.0, SearchIntel::score(100, 0.05, 0.10));
    }
}
