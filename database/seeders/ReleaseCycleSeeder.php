<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ReleaseCycle;
use Illuminate\Database\Seeder;

/**
 * The editorial release-cycle dataset: the seeder IS the editing interface (v1),
 * so every change is code-reviewed like the rest of the site.
 *
 * Rules for every row in here, no exceptions:
 *  - `source_url` is a real, checkable page that states the release date. First
 *    choice is the manufacturer's own newsroom / press release; where one could
 *    not be located, a dated secondary report is used and flagged in a comment.
 *  - `last_release_at` is the date the CURRENT model reached buyers where the
 *    source states one, otherwise the announcement date in that source (noted
 *    per row). Availability is what starts a price curve.
 *  - `next_expected_note` cites announced patterns and shipped history ONLY.
 *    No leaks, no supply-chain rumours, no "sources say". That fence is the
 *    plan's non-goal and it is what keeps this feature defensible.
 *  - `verified_at` is the day a human last checked the row against its source.
 *    Bump it on the quarterly re-verify; ReleaseCycle::scopeStale() and the
 *    verdict's confidence tier both read it.
 *
 * Idempotent (updateOrCreate by slug) so re-running refreshes rather than
 * duplicates: `php artisan db:seed --class=ReleaseCycleSeeder`.
 *
 * @see docs/plans/06-buy-or-wait.md §Phase 6.1
 */
class ReleaseCycleSeeder extends Seeder
{
    /** All rows below were verified against their sources on this date. */
    private const VERIFIED_AT = '2026-07-22';

    public function run(): void
    {
        foreach ($this->cycles() as $cycle) {
            $categorySlug = $cycle['category_slug'];
            unset($cycle['category_slug']);

            // Category is a soft link: a site that hasn't created the hub yet
            // still gets the cycle, just without the fallback mapping.
            $cycle['category_id'] = $categorySlug
                ? Category::where('slug', $categorySlug)->value('id')
                : null;

            $cycle['verified_at'] = self::VERIFIED_AT;

            ReleaseCycle::updateOrCreate(['slug' => $cycle['slug']], $cycle);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cycles(): array
    {
        return [
            [
                'slug' => 'iphone',
                'name' => 'iPhone',
                // No phones hub on the site, so category stays null and the strip
                // relies on the name match. Add the link if a hub is created.
                'category_slug' => null,
                'typical_month' => 9,
                'cadence_months' => 12,
                'last_release_name' => 'iPhone 17',
                'last_release_at' => '2025-09-19', // on sale; announced Sept 9, 2025
                'next_expected_note' => 'Apple has announced a new iPhone in September every year since 2012.',
                'source_url' => 'https://www.apple.com/newsroom/2025/09/apple-debuts-iphone-17/',
            ],
            [
                'slug' => 'galaxy-s',
                'name' => 'Samsung Galaxy S',
                'category_slug' => null,
                'typical_month' => 2,
                'cadence_months' => 12,
                'last_release_name' => 'Galaxy S26',
                'last_release_at' => '2026-03-11', // on sale; Unpacked Feb 25, 2026
                'next_expected_note' => 'Samsung has held a Galaxy S Unpacked in January or February every year since 2019; the S26 was unveiled on February 25, 2026 and reached shelves two weeks later.',
                'source_url' => 'https://news.samsung.com/global/galaxy-unpacked-2026-a-first-look-at-the-galaxy-s26-series-samsungs-most-intuitive-ai-phone-yet',
            ],
            [
                'slug' => 'pixel',
                'name' => 'Google Pixel',
                'category_slug' => null,
                'typical_month' => 8,
                'cadence_months' => 12,
                'last_release_name' => 'Pixel 10',
                'last_release_at' => '2025-08-28', // on sale; announced Aug 20, 2025
                'next_expected_note' => 'Google moved its Made by Google hardware event to August for the Pixel 9 and kept it there for the Pixel 10.',
                'source_url' => 'https://blog.google/products-and-platforms/devices/pixel/made-by-google-2025/',
            ],
            [
                'slug' => 'airpods-pro',
                'name' => 'AirPods Pro',
                'category_slug' => 'audio',
                'typical_month' => 9,
                // Three years, not one. The single most useful fact on this
                // page for anyone about to "wait for the new ones".
                'cadence_months' => 36,
                'last_release_name' => 'AirPods Pro 3',
                'last_release_at' => '2025-09-19', // on sale; announced Sept 9, 2025
                'next_expected_note' => 'Apple has refreshed AirPods Pro roughly every three years: October 2019, September 2022, September 2025.',
                'source_url' => 'https://www.apple.com/newsroom/2025/09/introducing-airpods-pro-3-the-ultimate-audio-experience/',
            ],
            [
                'slug' => 'ipad',
                'name' => 'iPad',
                'category_slug' => 'computers',
                'typical_month' => 3,
                'cadence_months' => 24,
                'last_release_name' => 'iPad (A16, 11th generation)',
                'last_release_at' => '2025-03-12', // on sale; announced March 4, 2025
                'next_expected_note' => "Apple's entry iPad refreshes irregularly: 13 months between the 9th and 10th generations, 29 months between the 10th and 11th.",
                // Secondary source: no apple.com newsroom permalink for the
                // base-iPad announcement could be located (the iPad Air release
                // from the same day is indexed, this one is not). Replace with
                // the Apple URL at the next re-verify if it surfaces.
                'source_url' => 'https://www.macrumors.com/2025/03/04/new-ipad-with-a16-and-more-storage/',
            ],
            [
                'slug' => 'macbook-air',
                'name' => 'MacBook Air',
                'category_slug' => 'computers',
                'typical_month' => 3,
                'cadence_months' => 12,
                'last_release_name' => 'MacBook Air (M5)',
                'last_release_at' => '2026-03-11', // on sale; announced March 3, 2026
                'next_expected_note' => "Apple has moved MacBook Air to a new chip each spring since the M2: M3 in March 2024, M4 in March 2025, M5 in March 2026.",
                'source_url' => 'https://www.apple.com/newsroom/2026/03/apple-introduces-the-new-macbook-air-with-m5/',
            ],
            [
                'slug' => 'sony-wh-1000x',
                'name' => 'Sony WH-1000X',
                'category_slug' => 'audio',
                'typical_month' => 5,
                // 21–36 months between the last three; 30 is the honest middle.
                'cadence_months' => 30,
                'last_release_name' => 'Sony WH-1000XM6',
                'last_release_at' => '2025-05-15', // announcement date; the press release states no separate on-sale date
                'next_expected_note' => "Sony's flagship 1000X headphones have refreshed 21 to 36 months apart: XM4 in August 2020, XM5 in May 2022, XM6 in May 2025.",
                'source_url' => 'https://www.prnewswire.com/news-releases/sony-introduces-the-best-noise-cancellation-with-the-wh-1000xm6-headphones-302456593.html',
            ],
            [
                'slug' => 'nintendo-switch',
                'name' => 'Nintendo Switch',
                'category_slug' => 'gaming',
                'typical_month' => 6,
                // Console generation, not an annual refresh: eight years between
                // the original Switch (March 2017) and Switch 2.
                'cadence_months' => 96,
                'last_release_name' => 'Nintendo Switch 2',
                'last_release_at' => '2025-06-05',
                'next_expected_note' => 'Nintendo ran eight years between the original Switch (March 2017) and Switch 2 (June 2025); a successor is not a near-term consideration.',
                'source_url' => 'https://www.nintendo.co.jp/corporate/release/en/2025/250402.html',
            ],
            [
                'slug' => 'gopro-hero',
                'name' => 'GoPro HERO',
                'category_slug' => 'cameras',
                'typical_month' => 9,
                'cadence_months' => 12,
                'last_release_name' => 'GoPro HERO13 Black',
                'last_release_at' => '2024-09-10', // retail availability; announced Sept 4, 2024
                'next_expected_note' => 'GoPro has not shipped a new HERO flagship since HERO13 Black in September 2024, breaking a near-annual cadence, so this line is past due rather than on schedule.',
                'source_url' => 'https://investor.gopro.com/press-releases/press-release-details/2024/GoPro-Announces-Two-New-Cameras-The-399-HERO13-Black-and-the-199-HERO/default.aspx',
            ],
            [
                'slug' => 'kindle-paperwhite',
                'name' => 'Kindle Paperwhite',
                'category_slug' => 'computers',
                'typical_month' => 10,
                'cadence_months' => 36,
                'last_release_name' => 'Kindle Paperwhite (12th generation)',
                'last_release_at' => '2024-10-16',
                'next_expected_note' => 'Amazon has refreshed the Paperwhite about every three years: 2018, 2021, 2024.',
                'source_url' => 'https://press.aboutamazon.com/canada-press-center/2024/10/amazon-launches-entirely-new-kindle-lineup-including-reimagined-kindle-scribe',
            ],
        ];
    }
}
