<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_route_logs_a_click_and_redirects_to_amazon(): void
    {
        config(['services.amazon.affiliate_tag' => 'gadgetdroptec-20']);

        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $product = Product::create([
            'category_id'   => $category->id,
            'name'          => 'Test Gadget',
            'asin'          => 'B00TEST123',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST123',
            'image_url'     => 'https://example.com/img.jpg',
            'price'         => 19.99,
            'description'   => 'A test gadget.',
        ]);

        $response = $this->get("/out/{$product->id}");

        $response->assertRedirect();
        $this->assertStringContainsString('tag=gadgetdroptec-20', $response->headers->get('Location'));

        $this->assertDatabaseHas('affiliate_clicks', ['product_id' => $product->id]);
    }
}
