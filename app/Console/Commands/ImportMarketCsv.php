<?php

namespace App\Console\Commands;

use App\Services\MarketImportService;
use App\Support\MarketReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Imports an externally produced market price CSV (docs/MARKET-IMPORT.md is
 * the column contract) into the market-wide layer and merges matching ASINs
 * into the curated products layer. Not scheduled — run it when a CSV
 * arrives. Each real run writes a JSON report artifact under
 * storage/app/market/.
 */
class ImportMarketCsv extends Command
{
    protected $signature = 'market:import
                            {file : Path to the market price file, CSV or XLSX (relative to the project root or absolute)}
                            {--dry-run : Parse, validate, and simulate the import without writing anything}';

    protected $description = 'Import a market price CSV/XLSX into market_products/market_price_snapshots and merge matching ASINs into the curated price layer';

    public function handle(MarketImportService $importer): int
    {
        $file = $this->argument('file');
        $path = is_file($file) ? $file : base_path($file);

        if (! is_file($path)) {
            $this->error("File not found: {$file}. See docs/MARKET-IMPORT.md for the CSV contract.");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            // Import inside a transaction that always rolls back so parsing,
            // upserts, and the curated merge run for real without persisting
            // anything (the database cache store rolls back with them).
            DB::beginTransaction();
            try {
                $stats = $importer->import($path);
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            DB::rollBack();

            $this->printHeadline($stats);
            $this->comment('Dry run - nothing written.');

            return self::SUCCESS;
        }

        try {
            $stats = $importer->import($path);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printHeadline($stats);

        $artifact = MarketReport::path();
        File::ensureDirectoryExists(dirname($artifact));

        File::put($artifact, json_encode(
            MarketReport::build($stats),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        ).PHP_EOL);

        $this->info("Wrote {$artifact}");

        return self::SUCCESS;
    }

    private function printHeadline(array $stats): void
    {
        $totals = $stats['totals'];
        $asins = $stats['asins'];
        $curated = $stats['curated'];

        $this->info("Market import: {$stats['file']}");
        $this->line("Rows: {$totals['rows']} | Imported: {$totals['imported']} | Rejected: {$totals['rejected']} | Duplicates: {$totals['duplicates']}");
        $this->line("ASINs: {$asins['new']} new, {$asins['known']} known ({$asins['total_tracked']} tracked)");

        $merge = "Curated: {$curated['matched_asins']} matched — {$curated['price_changes']} price changes, {$curated['same_price_checks']} same-price checks";
        if ($curated['stale_skipped'] > 0) {
            $merge .= ", {$curated['stale_skipped']} stale skipped";
        }
        $this->line($merge);

        if ($stats['movers'] !== []) {
            $top = collect($stats['movers'])->sortByDesc('drop_pct')->first();
            $this->line(sprintf(
                'Biggest mover: %s -%s%% ($%s → $%s) | New lows: %d',
                $top['title'],
                number_format($top['drop_pct'], 1),
                number_format($top['previous'], 2),
                number_format($top['current'], 2),
                $stats['new_lows_total'],
            ));
        } elseif ($stats['new_lows_total'] > 0) {
            $this->line('New lows: '.$stats['new_lows_total']);
        }

        $shown = array_slice($stats['rejects'], 0, 5);
        foreach ($shown as $reject) {
            $this->comment("  rejected line {$reject['line']}: {$reject['reason']}");
        }
        if ($totals['rejected'] > count($shown)) {
            $this->comment('  … '.($totals['rejected'] - count($shown)).' more reject(s) in the report artifact.');
        }
    }
}
