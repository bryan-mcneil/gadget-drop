<?php

namespace Tests\Feature;

use App\Mail\WeeklyDigest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDigestTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['slug' => 'gadgets', 'name' => 'Gadgets']);

        $this->product = Product::create([
            'category_id'      => $category->id,
            'name'             => 'Widget Pro 2',
            'asin'             => 'B00WIDGET1',
            'affiliate_url'    => 'https://www.amazon.com/dp/B00WIDGET1',
            'image_url'        => 'https://example.com/widget.jpg',
            'price'            => 49.99,
            'price_checked_at' => now()->subDays(2),
        ]);

        $this->post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Widget Pro 2 Review: Worth It',
            'slug'         => 'widget-pro-2-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'excerpt'      => 'A closer look at the Widget Pro 2.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
        $this->post->products()->attach($this->product->id, ['display_order' => 1]);
    }

    public function test_spotlight_routes_through_affiliate_redirect_and_never_links_amazon_directly(): void
    {
        $html = (new WeeklyDigest('https://example.com/unsubscribe/tok'))->render();

        $this->assertStringContainsString(
            route('affiliate.redirect', $this->product) . '?post=' . $this->post->id,
            $html,
        );
        $this->assertStringNotContainsString('amazon.com', $html);
    }

    public function test_spotlight_shows_price_checked_date_instead_of_a_stored_price(): void
    {
        $html = (new WeeklyDigest('https://example.com/unsubscribe/tok'))->render();

        $this->assertStringContainsString('Price checked ' . now()->subDays(2)->format('M j'), $html);
        $this->assertStringNotContainsString('$49.99', $html);
    }

    public function test_digest_links_drop_price_game_deals_page_and_unsubscribe(): void
    {
        $html = (new WeeklyDigest('https://example.com/unsubscribe/tok'))->render();

        $this->assertStringContainsString(route('drop-price.index'), $html);
        $this->assertStringContainsString(route('deals'), $html);
        $this->assertStringContainsString('https://example.com/unsubscribe/tok', $html);
    }

    public function test_subject_leads_with_the_featured_post_title(): void
    {
        $mailable = new WeeklyDigest('https://example.com/unsubscribe/tok');

        $this->assertSame(
            "This week's drop: Widget Pro 2 Review: Worth It",
            $mailable->envelope()->subject,
        );
    }

    public function test_list_unsubscribe_header_is_set(): void
    {
        $mailable = new WeeklyDigest('https://example.com/unsubscribe/tok');

        $this->assertSame(
            '<https://example.com/unsubscribe/tok>',
            $mailable->headers()->text['List-Unsubscribe'],
        );
    }
}
