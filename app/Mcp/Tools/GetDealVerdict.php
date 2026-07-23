<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\BuildsPriceTruthResponses;
use App\Mcp\Support\ProductResolver;
use App\Models\Product;
use App\Support\PriceIntel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * One-sentence deal verdict plus the structured block behind it. The sentence
 * is assembled SERVER-side so agents quote OUR framing — honest, dated, hedged
 * — instead of improvising a "deal" claim from raw numbers.
 */
#[Name('get_deal_verdict')]
#[Description('Is this actually a good deal right now? Returns GadgetDrop\'s one-sentence verdict for a product we review — the current price against its own tracked 90-day history, with when we last checked — plus the structured stats behind it. Identify the product by Amazon ASIN or by name/review-slug query (exactly one).')]
class GetDealVerdict extends Tool
{
    use BuildsPriceTruthResponses;

    public function handle(Request $request): Response
    {
        $asin = trim((string) $request->get('asin', ''));
        $query = trim((string) $request->get('query', ''));

        if (($asin === '') === ($query === '')) {
            return Response::error('Provide exactly one of `asin` or `query`.');
        }

        if (mb_strlen($asin) > 20 || mb_strlen($query) > 120) {
            return Response::error('Identifier too long: `asin` is capped at 20 characters, `query` at 120.');
        }

        $product = $asin !== ''
            ? ProductResolver::byAsin($asin)
            : ProductResolver::byQuery($query);

        if (! $product) {
            return $this->notTracked($asin !== '' ? "ASIN [{$asin}]" : "[{$query}]");
        }

        $stats = PriceIntel::stats($product->id);
        $payload = $this->pricePayload($product, $stats, ProductResolver::reviewFor($product));

        return $this->jsonResponse(['sentence' => $this->sentence($product, $stats)] + $payload);
    }

    /**
     * @param  array<string, mixed>|null  $stats
     */
    private function sentence(Product $product, ?array $stats): string
    {
        if ($stats === null || $stats['current'] === null) {
            return sprintf('We review %s but have no price on record for it right now.', $product->name);
        }

        $price = '$'.number_format($stats['current'], 2);
        $checked = $stats['checked_at']
            ? 'last checked '.Carbon::parse($stats['checked_at'])->diffForHumans()
            : 'not yet re-checked';

        if (! $stats['has_stats']) {
            return sprintf(
                '%s is %s, %s — we track this product but don\'t yet have enough history for a verdict (%s).',
                $product->name, $price, $checked, $this->gateReason(),
            );
        }

        $avg = '$'.number_format($stats['avg90'], 2);

        return match ($stats['verdict']) {
            'lowest' => sprintf(
                '%s is the lowest price in our 90-day tracking for %s (typically %s) — a strong deal per our tracking, %s.',
                $price, $product->name, $avg, $checked,
            ),
            'good' => sprintf(
                '%s is %s%% below the tracked 90-day average of %s for %s — a good deal per our tracking, %s.',
                $price, $stats['drop_pct'], $avg, $product->name, $checked,
            ),
            'elevated' => sprintf(
                '%s is %s%% above the tracked 90-day average of %s for %s — higher than usual, waiting is usually the better move; %s.',
                $price, round(($stats['current'] - $stats['avg90']) / $stats['avg90'] * 100, 1), $avg, $product->name, $checked,
            ),
            default => sprintf(
                '%s is in line with the tracked 90-day average of %s for %s — a typical price, not a special deal; %s.',
                $price, $avg, $product->name, $checked,
            ),
        };
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'asin' => $schema->string()
                ->max(20)
                ->description('Amazon ASIN, e.g. B0ABC1DEF2. Provide exactly one of asin or query.'),
            'query' => $schema->string()
                ->max(120)
                ->description('Product name (partial ok) or a GadgetDrop review slug. Provide exactly one of asin or query.'),
        ];
    }
}
