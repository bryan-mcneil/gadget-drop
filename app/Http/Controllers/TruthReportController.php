<?php

namespace App\Http\Controllers;

use App\Support\TruthReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Public Truth Report pages — post-event sale analysis rendered straight
 * from the stored JSON artifact (storage/app/truth/{slug}.json). The page
 * never recomputes: the numbers that were generated (and reviewed) are the
 * numbers that render. Publication is double-gated by TruthReport::published()
 * (config flag + artifact file). Editorial-integrity surface: NO affiliate
 * links anywhere on these pages — product names link to our reviews, which
 * hold the single CTA.
 */
class TruthReportController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $published = TruthReport::published();

        abort_if($published === [], 404);

        // While only one report exists an index page is a thin wrapper —
        // send readers straight to the report itself.
        if (count($published) === 1) {
            return redirect()->route('truth.show', array_key_first($published));
        }

        view()->share('serverMeta', [
            'title' => 'Truth Reports | GadgetDrop',
            'description' => 'Post-event sale analysis built from our own recorded price history: how many of the deals we track were actually deals.',
            'og_image' => null,
            'og_type' => 'website',
            'canonical' => route('truth.index'),
        ]);

        return view('public.truth.index', ['reports' => $published]);
    }

    public function show(string $slug): View
    {
        $published = TruthReport::published();

        abort_unless(isset($published[$slug]), 404);

        $report = TruthReport::load($slug);

        abort_if($report === null, 404);

        $title = $published[$slug]['title'];
        $description = $this->metaDescription($report);

        view()->share('serverMeta', [
            'title' => "{$title} | GadgetDrop",
            'description' => $description,
            'og_image' => null,
            'og_type' => 'article',
            'canonical' => route('truth.show', $slug),
        ]);
        view()->share('serverJsonLd', $this->buildDatasetJsonLd($slug, $title, $description, $report));

        return view('public.truth.show', [
            'slug' => $slug,
            'title' => $title,
            'report' => $report,
        ]);
    }

    /** Meta description from the report's own numbers — denominator-forward. */
    private function metaDescription(array $report): string
    {
        $totals = $report['totals'];
        $realPct = $report['headline']['real_deal_pct'];

        if ($realPct === null) {
            return sprintf(
                'We tracked %d products through this event window; none cleared the bar for an honest verdict. The full ledger, from our own recorded prices.',
                $totals['tracked'],
            );
        }

        return sprintf(
            '%s%% of the %d deals we could judge were genuinely below their pre-event low. Per-product data from our own recorded price history — including the %d products we could not judge.',
            number_format($realPct, 1),
            $totals['judged'],
            $totals['unobserved'] + $totals['insufficient'],
        );
    }

    /**
     * schema.org Dataset — the report is literally a dataset (window,
     * per-product observations, generated_at), which answer engines index
     * far more readily than prose claims.
     */
    private function buildDatasetJsonLd(string $slug, string $title, string $description, array $report): string
    {
        $base = url('');

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => $title,
            'description' => $description,
            'url' => route('truth.show', $slug),
            'temporalCoverage' => "{$report['window']['from']}/{$report['window']['to']}",
            'dateCreated' => $report['generated_at'],
            'variableMeasured' => [
                'pre-event minimum price',
                'event-window minimum price',
                'deal classification',
            ],
            'creator' => [
                '@type' => 'Organization',
                '@id' => "{$base}/#organization",
                'name' => 'GadgetDrop',
                'url' => $base,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
