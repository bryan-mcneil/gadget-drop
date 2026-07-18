<?php

namespace App\Console\Commands;

use App\Support\TruthReport;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Post-event Truth Report generator (docs/plans/05-truth-report.md): crunches
 * stored price snapshots for a sale-event window into an auditable JSON
 * artifact at storage/app/truth/{slug}.json. Pure read over the snapshot
 * table — re-runnable for any window, no network calls. Publication is a
 * separate, config-explicit step (config/truth.php `publish`).
 */
class GenerateTruthReport extends Command
{
    protected $signature = 'truth:report
        {slug : Report slug, e.g. prime-day-2026 (becomes the artifact filename)}
        {--from= : Event window start, Y-m-d}
        {--to= : Event window end, Y-m-d (inclusive)}
        {--dry-run : Print the headline block without writing the JSON}';

    protected $description = 'Analyze price snapshots for a sale-event window and write storage/app/truth/{slug}.json.';

    public function handle(): int
    {
        $slug = $this->argument('slug');

        // The slug becomes a filename — keep it strictly kebab-case.
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            $this->error("Slug must be kebab-case (a-z, 0-9, hyphens), e.g. prime-day-2026 — got \"{$slug}\".");

            return self::FAILURE;
        }

        $from = $this->parseDate('from');
        $to = $this->parseDate('to');

        if ($from === null || $to === null) {
            return self::FAILURE;
        }

        if ($from->gt($to)) {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        $report = array_merge(
            ['slug' => $slug, 'generated_at' => now()->toIso8601String()],
            TruthReport::analyze($from, $to),
        );

        $this->printHeadline($report);

        if ($this->option('dry-run')) {
            $this->comment('Dry run - nothing written.');

            return self::SUCCESS;
        }

        $dir = storage_path('app/truth');
        File::ensureDirectoryExists($dir);

        $path = $dir.DIRECTORY_SEPARATOR."{$slug}.json";
        File::put($path, json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        ).PHP_EOL);

        $this->info("Wrote {$path}");

        return self::SUCCESS;
    }

    private function parseDate(string $option): ?Carbon
    {
        $value = (string) $this->option($option);

        if ($value === '') {
            $this->error("--{$option} is required (Y-m-d).");

            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return Carbon::parse($value);
            } catch (InvalidFormatException) {
                // Fall through to the shared error below.
            }
        }

        $this->error("--{$option} must be a valid Y-m-d date - got \"{$value}\".");

        return null;
    }

    private function printHeadline(array $report): void
    {
        $totals = $report['totals'];
        $classes = $report['classes'];
        $headline = $report['headline'];

        $pct = fn (?float $p) => $p === null ? 'n/a' : number_format($p, 1).'%';

        $this->info("Truth Report: {$report['slug']} ({$report['window']['from']} to {$report['window']['to']})");
        $this->line("Tracked: {$totals['tracked']} | Judged: {$totals['judged']} | Insufficient history: {$totals['insufficient']}");
        $this->line(sprintf(
            'Real deals: %d (%s) | Repackaged: %d (%s) | Worse: %d (%s)',
            $classes['real_deal']['count'], $pct($classes['real_deal']['pct']),
            $classes['repackaged']['count'], $pct($classes['repackaged']['pct']),
            $classes['worse']['count'], $pct($classes['worse']['pct']),
        ));

        $baseline = "pre-event {$report['baseline_days']}-day min";

        if ($headline['biggest_real_deal'] !== null) {
            $this->line(sprintf(
                'Biggest real deal: %s -%s vs %s',
                $headline['biggest_real_deal']['product'],
                $pct($headline['biggest_real_deal']['discount_pct']),
                $baseline,
            ));
        }

        if ($headline['biggest_markup'] !== null) {
            $this->line(sprintf(
                'Biggest markup: %s +%s vs %s',
                $headline['biggest_markup']['product'],
                $pct($headline['biggest_markup']['markup_pct']),
                $baseline,
            ));
        }

        if ($headline['median_discount_pct'] !== null) {
            $this->line('Median event discount: '.$pct($headline['median_discount_pct']));
        }
    }
}
