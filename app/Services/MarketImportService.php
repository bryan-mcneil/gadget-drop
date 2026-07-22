<?php

namespace App\Services;

use App\Models\MarketPriceSnapshot;
use App\Models\MarketProduct;
use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use SplFileObject;

/**
 * Streams a market price file — CSV or Excel .xlsx, first worksheet
 * (docs/MARKET-IMPORT.md is the column contract of record) — into the
 * market-wide layer: one market_products row per ASIN
 * ever seen, change-only market_price_snapshots, and — for ASINs matching a
 * curated products.asin — a price push through ProductObserver with
 * source='import' so the existing snapshot/PriceIntel machinery does its own
 * bookkeeping. Source-agnostic on purpose: the repo never scrapes anything;
 * whatever can produce the CSV can feed this.
 *
 * import() returns the stats array MarketReport::build() turns into the run
 * artifact. It throws on an unreadable file or a header that misses required
 * columns; bad DATA rows are rejected per-row with a reason and never abort
 * the run.
 */
class MarketImportService
{
    public const REQUIRED_COLUMNS = ['asin', 'title', 'price'];

    public const KNOWN_COLUMNS = [
        'asin', 'title', 'description', 'price', 'currency', 'brand',
        'list_price', 'rating', 'review_count', 'category', 'url', 'scraped_at',
    ];

    /** Optional fields that are nulled (and counted) when unparseable. */
    private const WARNED_FIELDS = ['list_price', 'rating', 'review_count', 'url', 'scraped_at'];

    private const CHUNK = 500;

    /** @var array<string, int> field => values nulled during this run */
    private array $warnings = [];

    /**
     * @return array<string, mixed> stats for MarketReport::build()
     */
    public function import(string $path): array
    {
        $rows = str_ends_with(strtolower($path), '.xlsx')
            ? $this->xlsxRows($path)
            : $this->csvRows($path);

        $this->warnings = array_fill_keys(self::WARNED_FIELDS, 0);
        $rejectsCap = (int) config('market.rejects_cap');

        $stats = [
            'file' => basename($path),
            'totals' => ['rows' => 0, 'imported' => 0, 'rejected' => 0, 'duplicates' => 0],
            'asins' => ['new' => 0, 'known' => 0, 'total_tracked' => 0],
            'curated' => ['matched_asins' => 0, 'products_updated' => 0, 'price_changes' => 0, 'same_price_checks' => 0, 'stale_skipped' => 0],
            'movers' => [],
            'movers_total' => 0,
            'new_lows' => [],
            'new_lows_total' => 0,
            'coverage' => [],
            'rejects' => [],
            'warnings' => [],
            'ignored_columns' => [],
        ];

        // The curated layer is a few dozen rows — load the ASIN map once.
        $curated = Product::query()->whereNotNull('asin')->get()->groupBy('asin');
        $matchedAsins = [];

        $map = null;
        $seen = [];
        $chunk = [];
        $record = 0;

        foreach ($rows as $cells) {
            $record++;

            if (array_filter($cells, fn ($cell) => trim($cell) !== '') === []) {
                continue; // blank row (even before the header) — counted so reject line numbers stay true
            }

            if ($map === null) {
                [$map, $stats['ignored_columns']] = $this->parseHeader($cells);

                continue;
            }

            $stats['totals']['rows']++;

            $parsed = $this->parseRow($cells, $map, $record);

            if (isset($parsed['reject'])) {
                $stats['totals']['rejected']++;
                if (count($stats['rejects']) < $rejectsCap) {
                    $stats['rejects'][] = $parsed['reject'];
                }

                continue;
            }

            $data = $parsed['data'];

            // Listing pages repeat ASINs (sponsored slots, carousels) — the
            // first occurrence wins, the rest are counted, not rejected.
            if (isset($seen[$data['asin']])) {
                $stats['totals']['duplicates']++;

                continue;
            }
            $seen[$data['asin']] = true;

            $chunk[] = $data;
            if (count($chunk) >= self::CHUNK) {
                $this->flushChunk($chunk, $curated, $matchedAsins, $stats);
                $chunk = [];
            }
        }

        if ($map === null) {
            throw new \RuntimeException('The file has no header row: '.basename($path));
        }

        if ($chunk !== []) {
            $this->flushChunk($chunk, $curated, $matchedAsins, $stats);
        }

        $stats['curated']['matched_asins'] = count($matchedAsins);
        $stats['asins']['total_tracked'] = MarketProduct::count();
        $stats['warnings'] = $this->warnings;
        $stats['coverage'] = $this->coverage($stats);

        return $stats;
    }

    /**
     * One row of raw string cells at a time; the first yielded row is the
     * header. Streams — never slurps the file.
     */
    private function csvRows(string $path): \Generator
    {
        $file = new SplFileObject($path, 'r');

        while (($row = $file->fgetcsv(',', '"', '')) !== false) {
            yield array_map(fn ($cell) => (string) $cell, $row);
        }
    }

    /**
     * First worksheet only. Date cells arrive as DateTime objects from the
     * reader and are rendered straight into the contract's timestamp format,
     * so a real Excel datetime column needs no formatting on the producer's
     * side.
     */
    private function xlsxRows(string $path): \Generator
    {
        $reader = new XlsxReader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield array_map($this->cellValueToString(...), $row->toArray());
                }

                break; // first worksheet only
            }
        } finally {
            $reader->close();
        }
    }

    private function cellValueToString(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \DateInterval => '', // time-only duration cells carry no usable data here
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Header cells are matched case- and order-insensitively; the first cell
     * is stripped of a UTF-8 BOM (Power Automate writes one on Windows).
     *
     * @return array{0: array<string, int>, 1: array<int, string>} column => index map, ignored column names
     */
    private function parseHeader(array $header): array
    {
        $raw = implode(',', array_map(fn ($cell) => (string) $cell, $header));

        if (str_contains($raw, "\x00")) {
            throw new \RuntimeException('The CSV header contains NUL bytes — the file is probably UTF-16. Re-export as UTF-8 (the "Write to CSV file" encoding setting in Power Automate).');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $map = [];
        $ignored = [];

        foreach ($header as $index => $name) {
            $name = strtolower(trim((string) $name));

            if ($name === '') {
                continue;
            }

            if (! in_array($name, self::KNOWN_COLUMNS, true)) {
                if (! in_array($name, $ignored, true)) {
                    $ignored[] = $name;
                }

                continue;
            }

            $map[$name] ??= $index;
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($map)));

        if ($missing !== []) {
            throw new \RuntimeException('The header row is missing required column(s): '.implode(', ', $missing).'. See docs/MARKET-IMPORT.md for the contract.');
        }

        return [$map, $ignored];
    }

    /**
     * Validate one data row against the contract. Required fields reject the
     * row; unparseable optional fields are nulled and counted as warnings.
     *
     * @return array{reject: array}|array{data: array}
     */
    private function parseRow(array $cells, array $map, int $record): array
    {
        $get = fn (string $column) => trim((string) ($cells[$map[$column] ?? -1] ?? ''));

        $asin = strtoupper($get('asin'));
        $reject = fn (string $reason) => ['reject' => [
            'line' => $record,
            'asin' => $asin === '' ? null : $asin,
            'reason' => $reason,
        ]];

        if (! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            return $reject('asin must be 10 alphanumeric characters — got "'.$get('asin').'"');
        }

        $title = $get('title');
        if ($title === '') {
            return $reject('title is empty');
        }

        // Scrapers usually capture the whole tile text as one block; when no
        // explicit description arrives, derive title + description from it.
        $description = $get('description') === '' ? null : mb_substr($get('description'), 0, 500);
        if ($description === null) {
            [$title, $description] = $this->splitTitleBlock($title);
        }

        $price = $this->money($get('price'));
        if ($price === null || $price <= 0) {
            return $reject('price is not a positive number: "'.$get('price').'"');
        }

        $currency = strtoupper($get('currency'));
        $accepted = strtoupper((string) config('market.currency'));
        if ($currency !== '' && $currency !== $accepted) {
            return $reject("currency must be {$accepted} — got \"{$currency}\"");
        }

        $listPrice = null;
        if ($get('list_price') !== '') {
            $listPrice = $this->money($get('list_price'));
            if ($listPrice === null || $listPrice <= 0) {
                $listPrice = null;
                $this->warnings['list_price']++;
            }
        }

        $rating = null;
        if ($get('rating') !== '') {
            $rating = is_numeric($get('rating')) && (float) $get('rating') >= 0 && (float) $get('rating') <= 5
                ? round((float) $get('rating'), 1)
                : null;
            if ($rating === null) {
                $this->warnings['rating']++;
            }
        }

        $reviewCount = null;
        $reviewRaw = str_replace(',', '', $get('review_count'));
        if ($reviewRaw !== '') {
            $reviewCount = ctype_digit($reviewRaw) ? (int) $reviewRaw : null;
            if ($reviewCount === null) {
                $this->warnings['review_count']++;
            }
        }

        $url = $get('url');
        if ($url !== '' && ! str_starts_with($url, 'https://')) {
            $url = '';
            $this->warnings['url']++;
        }

        $scrapedAt = now();
        if ($get('scraped_at') !== '') {
            $parsed = $this->timestamp($get('scraped_at'));
            if ($parsed === null) {
                $this->warnings['scraped_at']++;
            } else {
                $scrapedAt = $parsed;
            }
        }

        return ['data' => [
            'asin' => $asin,
            'title' => mb_substr($title, 0, 500),
            'description' => $description,
            'price' => $price,
            'brand' => $get('brand') === '' ? null : mb_substr($get('brand'), 0, 255),
            'category' => $get('category') === '' ? null : mb_substr($get('category'), 0, 255),
            'url' => $url === '' ? null : mb_substr($url, 0, 500),
            'list_price' => $listPrice,
            'rating' => $rating,
            'review_count' => $reviewCount,
            'scraped_at' => $scrapedAt,
        ]];
    }

    /**
     * Upsert one chunk of deduped rows: two grouped queries per chunk (known
     * products, historical min prices), then per-row writes.
     *
     * @param  Collection<string, Collection<int, Product>>  $curated
     */
    private function flushChunk(array $rows, $curated, array &$matchedAsins, array &$stats): void
    {
        $known = MarketProduct::whereIn('asin', array_column($rows, 'asin'))->get()->keyBy('asin');

        $mins = MarketPriceSnapshot::whereIn('market_product_id', $known->pluck('id'))
            ->selectRaw('market_product_id, min(price) as min_price')
            ->groupBy('market_product_id')
            ->pluck('min_price', 'market_product_id');

        foreach ($rows as $row) {
            $product = $known->get($row['asin']);

            if ($product === null) {
                $product = MarketProduct::create([
                    'asin' => $row['asin'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'brand' => $row['brand'],
                    'category' => $row['category'],
                    'url' => $row['url'],
                    'current_price' => $row['price'],
                    'list_price' => $row['list_price'],
                    'rating' => $row['rating'],
                    'review_count' => $row['review_count'],
                    'first_seen_at' => $row['scraped_at'],
                    'last_seen_at' => $row['scraped_at'],
                ]);

                MarketPriceSnapshot::create([
                    'market_product_id' => $product->id,
                    'price' => $row['price'],
                    'created_at' => $row['scraped_at'],
                ]);

                $stats['asins']['new']++;
            } else {
                $this->updateKnown($product, $row, $mins, $stats);
                $stats['asins']['known']++;
            }

            $stats['totals']['imported']++;

            $this->mergeToCurated($row, $curated, $matchedAsins, $stats);
        }
    }

    /**
     * current_price always tracks the latest observation, so "price changed"
     * is a plain comparison against it — snapshots stay change-only.
     */
    private function updateKnown(MarketProduct $product, array $row, $mins, array &$stats): void
    {
        $previous = (float) $product->current_price;
        $price = $row['price'];

        // Optional metadata only overwrites when the row actually has a
        // value — a source without a brand column must not erase brands.
        $meta = array_filter([
            'description' => $row['description'],
            'brand' => $row['brand'],
            'category' => $row['category'],
            'url' => $row['url'],
            'list_price' => $row['list_price'],
            'rating' => $row['rating'],
            'review_count' => $row['review_count'],
        ], fn ($value) => $value !== null);

        $product->fill($meta + ['title' => $row['title'], 'current_price' => $price]);
        $product->last_seen_at = $product->last_seen_at->max($row['scraped_at']);
        $product->save();

        if ($price === $previous) {
            return;
        }

        MarketPriceSnapshot::create([
            'market_product_id' => $product->id,
            'price' => $price,
            'created_at' => $row['scraped_at'],
        ]);

        if ($previous > 0 && ($previous - $price) / $previous >= (float) config('market.mover_drop_pct')) {
            $stats['movers_total']++;
            $stats['movers'][] = [
                'asin' => $row['asin'],
                'title' => $row['title'],
                'previous' => round($previous, 2),
                'current' => round($price, 2),
                'drop_pct' => round(($previous - $price) / $previous * 100, 1),
            ];

            // Evict the smallest drop once over the cap: memory stays bounded
            // on huge files and the biggest movers always survive.
            if (count($stats['movers']) > (int) config('market.movers_cap')) {
                usort($stats['movers'], fn ($a, $b) => $b['drop_pct'] <=> $a['drop_pct']);
                array_pop($stats['movers']);
            }
        }

        $min = $mins[$product->id] ?? null;
        if ($min !== null && $price < (float) $min) {
            $stats['new_lows_total']++;
            if (count($stats['new_lows']) < (int) config('market.new_lows_cap')) {
                $stats['new_lows'][] = [
                    'asin' => $row['asin'],
                    'title' => $row['title'],
                    'price' => round($price, 2),
                    'previous_low' => round((float) $min, 2),
                ];
            }
        }
    }

    /**
     * Push a row's price into every curated product sharing its ASIN, through
     * ProductObserver so the curated layer keeps its own rules (snapshot on
     * change, same-price check capped at one per day, PriceIntel flush).
     * Mirrors RefreshProductPrices::handle(). A scrape older than the
     * product's last check never regresses fresher data.
     *
     * @param  Collection<string, Collection<int, Product>>  $curated
     */
    private function mergeToCurated(array $row, $curated, array &$matchedAsins, array &$stats): void
    {
        $matches = $curated->get($row['asin']);

        if ($matches === null) {
            return;
        }

        $matchedAsins[$row['asin']] = true;

        foreach ($matches as $product) {
            if ($product->price_checked_at !== null && $row['scraped_at']->lt($product->price_checked_at)) {
                $stats['curated']['stale_skipped']++;

                continue;
            }

            ProductObserver::$source = 'import';

            try {
                if ((float) $product->price !== $row['price']) {
                    $product->update(['price' => $row['price']]);
                    $stats['curated']['price_changes']++;
                } else {
                    $product->forceFill(['price_checked_at' => now()])->save();
                    $stats['curated']['same_price_checks']++;
                }
            } finally {
                ProductObserver::$source = 'manual';
            }

            $stats['curated']['products_updated']++;
        }
    }

    private function coverage(array $stats): array
    {
        return [
            'tracked_asins' => $stats['asins']['total_tracked'],
            'seen_this_run' => $stats['totals']['imported'],
            'stale_asins' => MarketProduct::where('last_seen_at', '<', now()->subDays((int) config('market.stale_days')))->count(),
            'categories' => MarketProduct::whereNotNull('category')
                ->selectRaw('category, count(*) as n')
                ->groupBy('category')
                ->orderByDesc('n')
                ->orderBy('category')
                ->pluck('n', 'category')
                ->map(fn ($n) => (int) $n)
                ->all(),
        ];
    }

    /**
     * Split a scraped title block at its first natural boundary: a dash or
     * pipe with spaces around it (so hyphenated words — "65-Inch",
     * "WH-1000XM5" — never split), falling back to ", ". First segment is
     * the title, second is the description, the rest is dropped:
     *
     *   "Oura Ring 5 Sizing Kit - Size Before You Buy Oura Ring 5 - …"
     *     → ["Oura Ring 5 Sizing Kit", "Size Before You Buy Oura Ring 5"]
     *
     * A block with no boundary stays a title with a null description.
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitTitleBlock(string $block): array
    {
        $segments = preg_split('/\s+[-–—|]\s+|,\s+/u', $block, 3);
        $segments = array_values(array_filter(array_map(trim(...), $segments), fn ($s) => $s !== ''));

        return [
            $segments[0] ?? $block,
            isset($segments[1]) ? mb_substr($segments[1], 0, 500) : null,
        ];
    }

    /**
     * "$1,299.99" → 1299.99; ranges and prose ("29.99 - 49.99", "See price
     * in cart") come back null — never guessed at.
     */
    private function money(string $value): ?float
    {
        $clean = str_replace(['$', ',', ' '], '', $value);

        return $clean !== '' && is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * ISO 8601 or Y-m-d[ H:i:s] only (assumed UTC without an offset) — the
     * lenient Carbon::parse grammar is deliberately fenced off. A bare Excel
     * date serial (a datetime cell written without a date style, so the
     * reader hands over the raw number) is also accepted within 1970–2117.
     */
    private function timestamp(string $value): ?Carbon
    {
        if (is_numeric($value)) {
            $serial = (float) $value;

            // Excel serial days since 1899-12-30; 25569 = 1970-01-01.
            return $serial >= 25569 && $serial < 80000
                ? Carbon::create(1899, 12, 30)->addSeconds((int) round($serial * 86400))
                : null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }
}
