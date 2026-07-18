<?php

namespace Tests\Feature;

use App\Support\TruthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Public /truth pages render a stored JSON artifact (no recompute), gated by
 * the config + file double-lock. Fixtures write a schema-shaped artifact
 * directly — the analyzer that produces real ones is covered by TruthReportTest.
 */
class TruthPageTest extends TestCase
{
    use RefreshDatabase;

    private const SLUGS = ['phpunit-page', 'phpunit-page-two', 'phpunit-page-off'];

    private const TITLE = 'Prime Day Test: Were the Deals Real?';

    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteArtifacts();
    }

    protected function tearDown(): void
    {
        $this->deleteArtifacts();

        parent::tearDown();
    }

    private function deleteArtifacts(): void
    {
        foreach (self::SLUGS as $slug) {
            File::delete(TruthReport::path($slug));
        }
    }

    /** A full schema-shaped report body, per the TruthReport docblock. */
    private function artifact(): array
    {
        return [
            'slug' => 'phpunit-page',
            'generated_at' => '2026-08-01T09:00:00+00:00',
            'window' => ['from' => '2026-07-07', 'to' => '2026-07-08'],
            'baseline_days' => 30,
            'config' => [
                'min_baseline_days' => 14,
                'thresholds' => ['real_deal' => 0.95, 'worse' => 1.05],
            ],
            'totals' => ['tracked' => 5, 'judged' => 4, 'insufficient' => 1],
            'classes' => [
                'real_deal' => ['count' => 2, 'pct' => 50.0],
                'repackaged' => ['count' => 1, 'pct' => 25.0],
                'worse' => ['count' => 1, 'pct' => 25.0],
            ],
            'headline' => [
                'real_deal_pct' => 50.0,
                'biggest_real_deal' => ['product' => 'Widget A', 'post_slug' => 'widget-a-review', 'discount_pct' => 30.0],
                'biggest_markup' => ['product' => 'Widget D', 'post_slug' => null, 'markup_pct' => 12.0],
                'median_discount_pct' => 5.0,
            ],
            'products' => [
                ['product_id' => 1, 'name' => 'Widget A', 'post_slug' => 'widget-a-review', 'tracked_since' => '2026-05-01', 'classification' => 'real_deal', 'pre_min' => 100.0, 'pre_avg' => 104.5, 'event_min' => 70.0, 'discount_pct' => 30.0],
                ['product_id' => 2, 'name' => 'Widget B', 'post_slug' => 'widget-b-review', 'tracked_since' => '2026-05-10', 'classification' => 'repackaged', 'pre_min' => 80.0, 'pre_avg' => 82.0, 'event_min' => 79.0, 'discount_pct' => 1.3],
                ['product_id' => 3, 'name' => 'Widget D', 'post_slug' => null, 'tracked_since' => '2026-05-20', 'classification' => 'worse', 'pre_min' => 50.0, 'pre_avg' => 51.0, 'event_min' => 56.0, 'discount_pct' => -12.0],
                ['product_id' => 4, 'name' => 'Widget E', 'post_slug' => null, 'tracked_since' => '2026-07-01', 'classification' => 'insufficient', 'pre_min' => null, 'pre_avg' => null, 'event_min' => null, 'discount_pct' => null],
            ],
        ];
    }

    private function writeArtifact(string $slug, array $overrides = []): void
    {
        File::ensureDirectoryExists(dirname(TruthReport::path($slug)));
        File::put(TruthReport::path($slug), json_encode(
            array_merge($this->artifact(), ['slug' => $slug], $overrides),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ));
    }

    private function publish(string $slug = 'phpunit-page'): void
    {
        config(['truth.publish' => [$slug => ['title' => self::TITLE, 'published' => true]]]);
        $this->writeArtifact($slug);
    }

    public function test_an_unpublished_slug_404s(): void
    {
        $this->get('/truth/phpunit-page')->assertNotFound();

        // Artifact present but config flag off — still gated.
        config(['truth.publish' => ['phpunit-page' => ['title' => self::TITLE, 'published' => false]]]);
        $this->writeArtifact('phpunit-page');

        $this->get('/truth/phpunit-page')->assertNotFound();
    }

    public function test_a_published_slug_without_an_artifact_404s(): void
    {
        config(['truth.publish' => ['phpunit-page' => ['title' => self::TITLE, 'published' => true]]]);

        $this->get('/truth/phpunit-page')->assertNotFound();
    }

    public function test_a_published_report_renders_headline_and_denominator(): void
    {
        $this->publish();

        // The page must quote the thresholds STORED in the artifact (0.95),
        // never live config — tuning config later must not rewrite old reports.
        config(['truth.thresholds' => ['real_deal' => 0.80, 'worse' => 1.20]]);

        $this->get('/truth/phpunit-page')
            ->assertOk()
            ->assertSee(self::TITLE)
            ->assertSee('50.0%')
            ->assertSee('Of the 5 products we track')
            ->assertSee('Report generated Aug 1, 2026')
            ->assertSee('Not enough history')
            ->assertSee('5% below the product')
            ->assertDontSee('20% below the product')
            // These pages are meant to rank — noindex must never be set.
            ->assertDontSee('noindex');
    }

    public function test_an_all_insufficient_report_renders_the_empty_scoreboard(): void
    {
        config(['truth.publish' => ['phpunit-page' => ['title' => self::TITLE, 'published' => true]]]);
        $this->writeArtifact('phpunit-page', [
            'totals' => ['tracked' => 3, 'judged' => 0, 'insufficient' => 3],
            'classes' => [
                'real_deal' => ['count' => 0, 'pct' => null],
                'repackaged' => ['count' => 0, 'pct' => null],
                'worse' => ['count' => 0, 'pct' => null],
            ],
            'headline' => [
                'real_deal_pct' => null,
                'biggest_real_deal' => null,
                'biggest_markup' => null,
                'median_discount_pct' => null,
            ],
            'products' => [
                ['product_id' => 4, 'name' => 'Widget E', 'post_slug' => null, 'tracked_since' => '2026-07-01', 'classification' => 'insufficient', 'pre_min' => null, 'pre_avg' => null, 'event_min' => null, 'discount_pct' => null],
            ],
        ]);

        $this->get('/truth/phpunit-page')
            ->assertOk()
            // Hero takes the honest-empty branch...
            ->assertSee('empty scoreboard')
            // ...as does the meta description...
            ->assertSee('none had enough pre-event history to judge honestly')
            // ...and the grade bars (share of zero judged) never render.
            ->assertDontSee('How the deals graded out');
    }

    public function test_the_report_page_has_zero_affiliate_links(): void
    {
        $this->publish();

        $this->get('/truth/phpunit-page')
            ->assertOk()
            // Review links are the only outbound path for a product row.
            ->assertSee(route('posts.show', 'widget-a-review'), false)
            // No /out/{product} affiliate redirects anywhere on the page.
            ->assertDontSee('/out/', false);
    }

    public function test_json_ld_dataset_is_present(): void
    {
        $this->publish();

        $this->get('/truth/phpunit-page')
            ->assertOk()
            ->assertSee('"@type":"Dataset"', false)
            ->assertSee('"temporalCoverage":"2026-07-07/2026-07-08"', false);
    }

    public function test_sitemap_includes_only_published_reports(): void
    {
        config(['truth.publish' => [
            'phpunit-page' => ['title' => self::TITLE, 'published' => true],
            'phpunit-page-two' => ['title' => 'No Artifact Yet', 'published' => true],
            'phpunit-page-off' => ['title' => 'Not Published', 'published' => false],
        ]]);
        $this->writeArtifact('phpunit-page');
        $this->writeArtifact('phpunit-page-off');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('truth.show', 'phpunit-page'), false)
            ->assertDontSee('phpunit-page-two', false)
            ->assertDontSee('phpunit-page-off', false);
    }

    public function test_truth_index_404s_with_no_published_reports(): void
    {
        $this->get('/truth')->assertNotFound();
    }

    public function test_truth_index_redirects_to_a_single_published_report(): void
    {
        $this->publish();

        $this->get('/truth')->assertRedirect(route('truth.show', 'phpunit-page'));
    }

    public function test_truth_index_lists_multiple_published_reports(): void
    {
        config(['truth.publish' => [
            'phpunit-page' => ['title' => self::TITLE, 'published' => true],
            'phpunit-page-two' => ['title' => 'Black Friday Test Report', 'published' => true],
        ]]);
        $this->writeArtifact('phpunit-page');
        $this->writeArtifact('phpunit-page-two');

        $this->get('/truth')
            ->assertOk()
            ->assertSee(self::TITLE)
            ->assertSee('Black Friday Test Report');
    }
}
