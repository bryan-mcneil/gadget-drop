<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phases 1.2 + 1.4 — the verdict chip on the review product card, and the
 * methodology section + FAQ structured data the chips link to.
 *
 * The tracked-price widget's own rendering is covered by
 * PriceHistoryWidgetTest; these tests pin the CARD surface (sm chip beside
 * the price, the quiet methodology link, the single-CTA rule) and the
 * /how-we-review #deal-verdicts surface (copy quotes the real PriceIntel
 * gates, FAQPage JSON-LD parses).
 */
class VerdictSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private function makeReviewWithProduct(float $price = 100): array
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chipped Widget',
            'asin' => 'B00CHIP00',
            'affiliate_url' => 'https://www.amazon.com/dp/B00CHIP00',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $price,
            'description' => 'A widget.',
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Chipped Widget Review',
            'slug' => 'chipped-widget-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return [$post, $product];
    }

    /** Add a historical snapshot without touching products.price. */
    private function snapshot(Product $product, float $price, int $daysAgo): void
    {
        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => $price,
            'source' => 'manual',
            'created_at' => now()->subDays($daysAgo),
        ]);
        PriceIntel::flush($product->id);
    }

    public function test_card_shows_the_sm_chip_and_methodology_link_when_stats_qualify(): void
    {
        // Two snapshots spanning ≥14 days → gates pass; the current 149 is the
        // record low → 'lowest'.
        [$post, $product] = $this->makeReviewWithProduct(149);
        $this->snapshot($product, 179, 24);

        $this->assertSame('lowest', PriceIntel::stats($product->id)['verdict']);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            // The contiguous sm sizing string only the card chip emits (the
            // widget badge below the card is md: "text-[11px] px-2.5 py-1").
            ->assertSee('text-[10px] px-2 py-0.5', false)
            ->assertSee('How we call deals', false)
            // The full href pins the link to the anchor, not two loose substrings.
            ->assertSee(route('how-we-review').'#deal-verdicts', false);
    }

    public function test_card_without_stats_shows_no_chip_and_no_methodology_link(): void
    {
        // Only the observer's initial snapshot — one point, no span → verdict null.
        [$post] = $this->makeReviewWithProduct(120);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('View on Amazon', false) // the card itself still renders
            ->assertSee('$120', false)           // …price included: looks as before
            ->assertDontSee('text-[10px] px-2 py-0.5', false)
            ->assertDontSee('How we call deals', false)
            ->assertDontSee('#deal-verdicts', false);
    }

    public function test_the_card_still_holds_exactly_one_affiliate_link(): void
    {
        [$post, $product] = $this->makeReviewWithProduct(149);
        $this->snapshot($product, 179, 24);

        $response = $this->get("/posts/{$post->slug}")->assertOk();

        $cardCta = route('affiliate.redirect', ['product' => $product->id, 'post' => $post->id]);
        $this->assertSame(
            1,
            substr_count($response->getContent(), $cardCta),
            'The verdict chip/methodology link must not add a second affiliate CTA to the card.'
        );
    }

    public function test_the_methodology_section_documents_the_verdict_system(): void
    {
        $this->get('/how-we-review')
            ->assertOk()
            // The anchor the card chips, /deals, and the MCP server all cite.
            ->assertSee('id="deal-verdicts"', false)
            ->assertSee('Where the numbers come from', false)
            ->assertSee('The honesty gates', false)
            ->assertSee('The four verdicts', false)
            ->assertSee('The 30-day reference price', false)
            ->assertSee('What we never do', false)
            // The section renders the real <x-verdict-badge>, so the four
            // labels users see on chips are the ones documented here.
            ->assertSee('Lowest tracked price', false)
            ->assertSee('Below typical price', false)
            ->assertSee('Typical price', false)
            ->assertSee('Higher than usual', false);
    }

    public function test_the_methodology_copy_quotes_the_real_gates_and_thresholds(): void
    {
        // Built from the constants, so this test fails if the copy's numbers
        // ever stop matching what the code enforces.
        $this->get('/how-we-review')
            ->assertOk()
            ->assertSee(
                PriceIntel::MIN_POINTS.' price snapshots spanning at least '.PriceIntel::MIN_SPAN_DAYS.' days',
                false
            )
            ->assertSee('At least '.(PriceIntel::DEAL_PCT * 100).'% below the 90-day average', false)
            ->assertSee(
                'sits at least '.\App\Http\Controllers\DealsController::MIN_DROP_PCT.'% below its tracked',
                false
            )
            ->assertSee('scrape Amazon pages', false)
            ->assertSee('No MSRP theater', false);
    }

    public function test_the_page_emits_valid_faqpage_json_ld(): void
    {
        $html = $this->get('/how-we-review')->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertCount(1, $m[1], 'Exactly one JSON-LD block should be emitted on /how-we-review.');

        $data = json_decode($m[1][0], true);

        $this->assertIsArray($data, 'The FAQ JSON-LD must be parseable JSON.');
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(5, $data['mainEntity']);

        foreach ($data['mainEntity'] as $entity) {
            $this->assertSame('Question', $entity['@type']);
            $this->assertNotSame('', $entity['name']);
            $this->assertSame('Answer', $entity['acceptedAnswer']['@type']);
            $this->assertNotSame('', $entity['acceptedAnswer']['text']);
        }

        // The structured answers quote the same constants as the visible copy.
        $blob = $m[1][0];
        $this->assertStringContainsString(
            PriceIntel::MIN_POINTS.' price snapshots spanning at least '.PriceIntel::MIN_SPAN_DAYS.' days',
            $blob
        );
    }
}
