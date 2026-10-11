<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\BuildsPriceTruthResponses;
use App\Mcp\Support\ProductResolver;
use App\Models\ReleaseCycle;
use App\Support\BuyOrWait;
use App\Support\PriceIntel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * "Should I buy this now or wait?" is the question agents get asked constantly
 * and have no honest data for. This answers it from two sourced inputs: where a
 * product LINE sits in its shipped release cycle, and where our own tracked
 * price sits against its history.
 *
 * The sentence is assembled server-side so an agent quotes our hedged, dated
 * framing rather than turning a cadence number into a confident prediction about
 * an unannounced product. Every response carries the cycle's source URL and the
 * date a human last verified it.
 */
#[Name('get_buy_or_wait_verdict')]
#[Description('Should someone buy a gadget now or wait for the next model? Returns GadgetDrop\'s dated buy-or-wait verdict for a product line (iPhone, AirPods Pro, Nintendo Switch...): where the line sits in its documented release cycle, where our tracked price sits against its own history, the sourced factors behind the call, and a confidence level. Identify the line by name or slug.')]
class GetBuyOrWaitVerdict extends Tool
{
    use BuildsPriceTruthResponses;

    public function handle(Request $request): Response
    {
        $query = trim((string) $request->get('query', ''));

        if ($query === '') {
            return Response::error('Provide a `query` naming a product line, e.g. "iPhone" or "airpods-pro". '.$this->availableLines());
        }

        if (mb_strlen($query) > 120) {
            return Response::error('Identifier too long: `query` is capped at 120 characters.');
        }

        $cycle = $this->resolve($query);

        if (! $cycle) {
            return Response::error(sprintf(
                'GadgetDrop does not track a release cycle for [%s]. %s',
                $query,
                $this->availableLines(),
            ));
        }

        $product = $cycle->flagshipProduct();
        $stats = $product ? PriceIntel::stats($product->id) : null;
        $result = BuyOrWait::verdict($cycle, $stats);

        $payload = [
            'line' => $cycle->name,
            'slug' => $cycle->slug,
            'verdict' => $result['verdict'],
            'verdict_label' => BuyOrWait::label($result['verdict']),
            'confidence' => $result['confidence'],
            'sentence' => $result['sentence'],
            'as_of' => now()->toDateString(),
            'cycle' => [
                'current_model' => $cycle->last_release_name,
                'released_at' => $cycle->last_release_at?->toDateString(),
                'months_since_release' => (int) round($cycle->monthsSinceRelease()),
                'cadence_months' => $cycle->cadence_months,
                'cycle_position' => $result['cycle_position'],
                'typical_refresh_month' => $cycle->typical_month
                    ? Carbon::create(null, $cycle->typical_month, 1)->format('F')
                    : null,
                'pattern_note' => $cycle->next_expected_note,
                'source_url' => $cycle->source_url,
                'verified_at' => $cycle->verified_at?->toDateString(),
                'data_is_stale' => $cycle->isStale(),
            ],
            'factors' => $result['factors'],
        ];

        // Price half: the same block the other price tools return, so an agent
        // that already knows this shape needs no special handling. Null when no
        // product we review maps to the line: a cycle-only verdict, and the
        // confidence field above already says so.
        $payload['price'] = $product
            ? $this->pricePayload($product, $stats, ProductResolver::reviewFor($product))
            : null;

        return $this->jsonResponse($payload + [
            'page_url' => route('buy-or-wait.show', $cycle->slug),
            'methodology_url' => route('how-we-review').'#buy-or-wait',
            'disclosure' => $this->disclosure(),
            'note' => 'Release dates are editorial records sourced to the manufacturer\'s own announcement, not predictions. GadgetDrop publishes nothing about unannounced products; quote the verdict with its date and confidence.',
        ]);
    }

    /** Slug exact → name exact → name substring. Agents hold names, not our IDs. */
    private function resolve(string $query): ?ReleaseCycle
    {
        $needle = mb_strtolower($query);

        $bySlug = ReleaseCycle::where('slug', $needle)->first();

        if ($bySlug) {
            return $bySlug;
        }

        return ReleaseCycle::query()->get()->first(
            fn (ReleaseCycle $cycle) => mb_strtolower($cycle->name) === $needle
                || str_contains(mb_strtolower($cycle->name), $needle)
                || str_contains($needle, mb_strtolower($cycle->name)),
        );
    }

    /** Names the lines we DO cover, so a miss is a redirect rather than a dead end. */
    private function availableLines(): string
    {
        $lines = ReleaseCycle::query()->orderBy('name')->pluck('name')->all();

        if ($lines === []) {
            return 'No release cycles are published yet.';
        }

        return 'Lines we track: '.implode(', ', $lines).'.';
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->max(120)
                ->required()
                ->description('Product line name or slug, e.g. "iPhone", "AirPods Pro", "nintendo-switch". Partial names match.'),
        ];
    }
}
