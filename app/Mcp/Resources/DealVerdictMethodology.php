<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Resource;

/**
 * The deal-verdict methodology as an MCP resource. Renders the SAME Blade
 * partial /how-we-review includes (public/partials/deal-methodology) and strips
 * it to markdown-ish text — one copy of the prose, one set of gate numbers, no
 * fork between what humans and agents read.
 */
#[Name('methodology')]
#[Description('How GadgetDrop price stats and deal verdicts are computed: where the snapshots come from, the honesty gates that keep young data silent, the four verdict tiers, and what we never do (no MSRP theater, no scraping, no manufactured urgency). Identical content to the public methodology page.')]
class DealVerdictMethodology extends Resource
{
    protected string $uri = 'gadgetdrop://methodology/deal-verdicts';

    protected string $mimeType = 'text/markdown';

    public function handle(Request $request): Response
    {
        // truthReports: [] — the closing cross-promo paragraph is page furniture,
        // not methodology; the resource stays stable across report launches.
        $html = view('public.partials.deal-methodology', ['truthReports' => []])->render();

        return Response::text($this->toText($html));
    }

    /** Heading tags become markdown headings; everything else strips to prose. */
    private function toText(string $html): string
    {
        $html = preg_replace('/<h2\b[^>]*>/', "\n## ", $html);
        $html = preg_replace('/<h3\b[^>]*>/', "\n### ", $html);
        $html = preg_replace('/<\/(h2|h3|p|div|section|span)>/', "$0\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        // Collapse the Blade indentation: trim every line, drop runs of blanks.
        $lines = array_map(trim(...), explode("\n", $text));
        $out = [];
        foreach ($lines as $line) {
            if ($line === '' && (end($out) === '' || $out === [])) {
                continue;
            }
            $out[] = $line;
        }

        $body = trim(implode("\n", $out));

        return $body."\n\nSource: ".route('how-we-review').'#deal-verdicts (the public version of this methodology)'."\n";
    }
}
