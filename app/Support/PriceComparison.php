<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Turns the raw structured payload from a live retailer price search into the
 * view model the widget renders. Every claim a reader sees as fact is made
 * HERE, in PHP, from the same rows the table prints: the model finds and reads
 * listings, it never compares, ranks, or writes the verdict sentence.
 *
 * Three invariants this class exists to enforce (plan 11, Compliance/Honesty):
 *
 *  1. AMAZON NEVER COMES FROM SEARCH. Associates 2(b) permits an Amazon price
 *     only when Amazon serves the link or the data came from PA-API. The
 *     Amazon row is our OWN tracked price, passed in as $ourPrice. The tool is
 *     given an allowed_domains whitelist, and {@see self::matchHost()} then
 *     re-checks every returned row against that same list: a retailer we did
 *     not ask for is a bug, not a result.
 *  2. THE CLAIM IS GATED, NOT THE FEATURE. The table always renders. The
 *     "X is lowest" line renders only when our own price was checked within
 *     $freshDays, because competitor prices are live while ours may be days
 *     old. The error worth engineering against is not "we wrongly say Walmart
 *     wins", it is "we wrongly say AMAZON wins": that is the error that
 *     flatters our own payout and sends a reader to a mismatched checkout.
 *  3. NOTHING HERE IS PERSISTED. This is a pure transform. No writes, no
 *     history, no alerting (Associates 6(y)).
 *
 * Pure by design, in the {@see ArticleBody} / {@see BuyOrWait} style: no
 * facades, no Eloquent, no container. If this file ever needs the framework
 * booted to test, it has grown a dependency it is not supposed to have.
 *
 * @see docs/plans/11-live-price-compare.md Phase 11.1
 */
class PriceComparison
{
    /** Enough to show the market without turning the widget into a directory. */
    public const MAX_ROWS = 6;

    /**
     * A row priced above this multiple of our own is a mis-parse, not a deal
     * (a "$1,899" grabbed off a $19 listing). Deliberately one-sided: an
     * absurdly LOW mis-parse errs toward crowning a retailer, which is the
     * safe direction, and a hard floor would suppress genuine clearances.
     */
    private const ABSURD_MULTIPLE = 100;

    private const MAX_RETAILER_LENGTH = 60;

    private const MAX_URL_LENGTH = 2048;

    /**
     * @param  array<string, mixed>  $raw  decoded model payload
     * @param  array<int, string>  $allowedHosts  registrable domains from config('price-compare.retailers')
     * @param  CarbonInterface|null  $now  injected clock, so the freshness gate is testable without a facade
     * @return array{
     *     rows: array<int, array{retailer: string, price: float, url: string, in_stock: bool}>,
     *     retailers_checked: int,
     *     our_price: float|null,
     *     our_checked_at: CarbonInterface|null,
     *     is_fresh: bool,
     *     winner: string|null,
     *     winner_label: string|null,
     *     is_empty: bool,
     *     suppressed: bool
     * }
     */
    public static function normalize(
        array $raw,
        ?float $ourPrice,
        ?CarbonInterface $ourCheckedAt,
        array $allowedHosts,
        int $freshDays = 7,
        float $confidenceFloor = 0.6,
        ?CarbonInterface $now = null,
    ): array {
        $allowed = self::allowedHosts($allowedHosts);
        $now ??= Carbon::now();

        // abs(): a future-dated stamp is clock skew at small values and bad
        // data at large ones, and neither should read as "freshly checked".
        $isFresh = $ourCheckedAt !== null
            && abs($now->getTimestamp() - $ourCheckedAt->getTimestamp()) <= $freshDays * 86400;

        $base = [
            'rows' => [],
            'retailers_checked' => self::retailersChecked($raw, $allowed),
            'our_price' => $ourPrice,
            'our_checked_at' => $ourCheckedAt,
            'is_fresh' => $isFresh,
            'winner' => null,
            'winner_label' => null,
            'is_empty' => false,
            'suppressed' => false,
        ];

        // Fail closed: a payload that does not carry its own confidence is
        // malformed, and a malformed payload is not evidence of anything.
        if (! self::isConfident($raw, $confidenceFloor)) {
            return ['suppressed' => true] + $base;
        }

        $rows = self::rows($raw, $ourPrice, $allowed);

        if ($rows === []) {
            return ['is_empty' => true] + $base;
        }

        [$winner, $label] = self::winner($rows, $ourPrice, $isFresh);

        return [
            'rows' => $rows,
            'winner' => $winner,
            'winner_label' => $label,
        ] + $base;
    }

    /**
     * Surviving rows, cheapest first. Each filter drops silently: a row we
     * cannot fully vouch for simply does not exist as far as the reader is
     * concerned.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<int, string>  $allowed
     * @return array<int, array{retailer: string, price: float, url: string, in_stock: bool}>
     */
    private static function rows(array $raw, ?float $ourPrice, array $allowed): array
    {
        $results = $raw['results'] ?? null;

        if (! is_array($results)) {
            return [];
        }

        $byHost = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            // The model's own variant self-report is the only guard we have
            // against a 128GB price on a 256GB review, so anything short of an
            // explicit true is a miss.
            if (($result['exact_model_match'] ?? null) !== true) {
                continue;
            }

            $price = self::price($result['price'] ?? null, $ourPrice);

            if ($price === null) {
                continue;
            }

            $url = self::url($result['url'] ?? null);

            if ($url === null) {
                continue;
            }

            $host = self::matchHost($url, $allowed);

            if ($host === null) {
                continue;
            }

            // Keyed on the matched whitelist domain, never on the model's
            // free-text retailer name: "Walmart" and "Walmart.com" are one
            // retailer and must not both take a row.
            if (isset($byHost[$host]) && $byHost[$host]['price'] <= $price) {
                continue;
            }

            $byHost[$host] = [
                'retailer' => self::retailer($result['retailer'] ?? null, $host),
                'price' => $price,
                'url' => $url,
                // Absence is not evidence of being out of stock.
                'in_stock' => ! array_key_exists('in_stock', $result) || (bool) $result['in_stock'],
            ];
        }

        $rows = array_values($byHost);

        usort($rows, fn (array $a, array $b) => $a['price'] <=> $b['price']);

        return array_slice($rows, 0, self::MAX_ROWS);
    }

    /**
     * The verdict, or silence. Computed only when our own price is fresh
     * enough to be compared against live ones, and only from IN-STOCK rows:
     * naming an unbuyable listing "currently the lowest" is precisely the kind
     * of confident-and-wrong claim this widget exists to avoid.
     *
     * @param  array<int, array{retailer: string, price: float, url: string, in_stock: bool}>  $rows
     * @return array{0: string|null, 1: string|null}
     */
    private static function winner(array $rows, ?float $ourPrice, bool $isFresh): array
    {
        if (! $isFresh || $ourPrice === null || $ourPrice <= 0) {
            return [null, null];
        }

        $inStock = array_values(array_filter($rows, fn (array $row) => $row['in_stock']));

        if ($inStock === []) {
            return [null, null];
        }

        $best = $inStock[0];   // already sorted ascending
        $ours = self::money($ourPrice);
        $theirs = self::money($best['price']);

        // No em dashes: this copy is editorial prose on the page and is quoted
        // verbatim by AI agents (CONTENT-GUIDELINES.md).
        if ($best['price'] < $ourPrice) {
            return ['retailer', sprintf(
                '%s has the lowest in-stock price we found at %s, %s under our tracked Amazon price of %s.',
                $best['retailer'],
                $theirs,
                self::money($ourPrice - $best['price']),
                $ours,
            )];
        }

        // Ties go to Amazon: at the same number it is the one we can actually
        // link, and the one whose price we track ourselves.
        if ($best['price'] === $ourPrice) {
            return ['amazon', sprintf(
                'Our tracked Amazon price of %s matches the lowest in-stock price we found elsewhere (%s at %s).',
                $ours,
                $theirs,
                $best['retailer'],
            )];
        }

        return ['amazon', sprintf(
            'Our tracked Amazon price of %s is the lowest, %s under the best in-stock price we found elsewhere (%s at %s).',
            $ours,
            self::money($best['price'] - $ourPrice),
            $theirs,
            $best['retailer'],
        )];
    }

    /**
     * A usable price, or null. `> 0` rather than `>= 0` on purpose: a free
     * listing is a parse error or a bundle add-on, never a competitor price.
     */
    private static function price(mixed $value, ?float $ourPrice): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $price = (float) $value;

        if (! is_finite($price)) {
            return null;
        }

        $price = round($price, 2);

        if ($price <= 0) {
            return null;
        }

        if ($ourPrice !== null && $ourPrice > 0 && $price > $ourPrice * self::ABSURD_MULTIPLE) {
            return null;
        }

        return $price;
    }

    /** An http(s) URL of sane length, or null. Anything else is not a source. */
    private static function url(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
            return null;
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: ''));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * The whitelist entry this URL belongs to, or null. Registrable-suffix
     * match, so `walmart.com`, `www.walmart.com` and `shop.walmart.com` all
     * resolve to `walmart.com` while `walmart.com.example.net` does not
     * resolve at all.
     *
     * @param  array<int, string>  $allowed
     */
    private static function matchHost(string $url, array $allowed): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = rtrim(strtolower($host), '.');

        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * The retailer's display name. Model-authored, so it is trimmed, flattened
     * and capped before it is ever printed; an unusable one falls back to the
     * matched domain, which is always true by construction.
     */
    private static function retailer(mixed $value, string $host): string
    {
        if (! is_string($value)) {
            return $host;
        }

        // Whitespace first, so a newline becomes a word break rather than
        // welding two words together; then strip whatever control characters
        // are left (nulls, zero-width joiners, bidi overrides).
        $name = trim((string) preg_replace('/\s+/u', ' ', $value));
        $name = trim((string) preg_replace('/[\p{C}]/u', '', $name));

        return $name === '' ? $host : mb_substr($name, 0, self::MAX_RETAILER_LENGTH);
    }

    /**
     * How many retailers we tell the reader we checked. Clamped to the size of
     * the whitelist we actually handed the search: the model may report fewer,
     * but it can never inflate a number we render as our own claim.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<int, string>  $allowed
     */
    private static function retailersChecked(array $raw, array $allowed): int
    {
        $ceiling = count($allowed);
        $claimed = $raw['retailers_checked'] ?? null;

        if (! is_int($claimed) && ! (is_string($claimed) && ctype_digit($claimed))) {
            return $ceiling;
        }

        return max(0, min((int) $claimed, $ceiling));
    }

    /** @param  array<string, mixed>  $raw */
    private static function isConfident(array $raw, float $floor): bool
    {
        $confidence = $raw['confidence'] ?? null;

        if (! is_int($confidence) && ! is_float($confidence) && ! (is_string($confidence) && is_numeric($confidence))) {
            return false;
        }

        return (float) $confidence >= $floor;
    }

    /**
     * Config entries normalised the same way hosts are, so a stray `www.` or a
     * capital in the config cannot silently disable a retailer.
     *
     * @param  array<int, string>  $hosts
     * @return array<int, string>
     */
    private static function allowedHosts(array $hosts): array
    {
        $clean = [];

        foreach ($hosts as $host) {
            if (! is_string($host)) {
                continue;
            }

            $host = (string) preg_replace('/^www\./', '', rtrim(strtolower(trim($host)), '.'));

            if ($host !== '') {
                $clean[] = $host;
            }
        }

        return array_values(array_unique($clean));
    }

    private static function money(float $value): string
    {
        return '$'.number_format($value, 2);
    }
}
