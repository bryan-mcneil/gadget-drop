<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\BuildsPriceTruthResponses;
use App\Mcp\Support\ProductResolver;
use App\Support\PriceIntel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Full tracked price history for one product: current price + checked_at,
 * 30/90-day low/avg/high, the daily-series sparkline points, and the verdict.
 * Thin wrapper over PriceIntel::stats() (db-cached 6h) — a call should cost no
 * more than a page view.
 */
#[Name('get_price_history')]
#[Description('Get GadgetDrop\'s tracked price history for a product we review: current price, when we last checked it, 30/90-day low/average/high, a dated price series, and our deal verdict. Identify the product by Amazon ASIN or by name/review-slug query (exactly one). Stats are null until the product has enough history — that restraint is deliberate; quote the reason.')]
class GetPriceHistory extends Tool
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

        return $this->jsonResponse($this->pricePayload(
            $product,
            PriceIntel::stats($product->id),
            ProductResolver::reviewFor($product),
            withPoints: true,
        ));
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
