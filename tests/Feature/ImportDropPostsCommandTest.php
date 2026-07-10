<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\DailyDropImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the posts:import command and the importer's per-type handling.
 * Note: the sqlite test schema's posts.type CHECK predates tech_news, so
 * these tests exercise the non-article branch with tech_tip only.
 */
class ImportDropPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'name' => config('site.author.name'),
            'slug' => config('site.author.slug'),
        ]);
    }

    private function fixture(): array
    {
        return [
            [
                'title' => 'Anker 737 Power Bank Review: Worth the Premium Price?',
                'excerpt' => 'The Anker 737 packs 24,000mAh and 140W output. Here is who should buy it and who should grab the cheaper step-down option instead.',
                'body' => "Owners consistently report solid results.\n\n## What Is the Anker 737?\n\nA big battery. See [our Belkin review](/posts/belkin-review).",
                'type' => 'article',
                'author_name' => config('site.author.name'),
                'category_name' => 'Computers & Accessories',
                'tag_names' => ['power banks', 'anker'],
                'product_asin' => 'B0ABCDEFGH',
                'rating' => 4.4,
                'pros' => ['140W output charges laptops'],
                'cons' => ['Heavy at 1.4 lb'],
                'seo' => [
                    'meta_title' => 'Anker 737 Power Bank Review',
                    'meta_description' => 'Is the Anker 737 worth it? Owner feedback, spec analysis, and who should buy the 24,000mAh 140W power bank.',
                    'focus_keyword' => 'anker 737 review',
                    'target_query' => 'anker 737 power bank review',
                    'slug' => 'anker-737-power-bank-review',
                ],
            ],
            [
                'title' => 'Fix Slow Wi-Fi on Windows 11: Settings That Actually Help',
                'excerpt' => 'Slow Wi-Fi on Windows 11 usually traces to power management or band steering. These settings changes fix the most common causes.',
                'body' => "Slow Wi-Fi has a few usual suspects.\n\n## Fix Slow Wi-Fi on Windows 11\n\n1. Open Device Manager.",
                'type' => 'tech_tip',
                'author_name' => config('site.author.name'),
                'category_name' => 'Computers & Accessories',
                'tag_names' => ['windows 11', 'wi-fi'],
                'source_url' => 'https://www.reddit.com/r/techsupport/comments/example',
                'seo' => [
                    'meta_title' => 'Fix Slow Wi-Fi on Windows 11',
                    'meta_description' => 'Slow Wi-Fi on Windows 11? These driver and power settings fix the most common causes in under ten minutes.',
                    'focus_keyword' => 'slow wifi windows 11',
                    'slug' => 'fix-slow-wifi-windows-11',
                ],
            ],
        ];
    }

    private function writeFixture(?array $posts = null): string
    {
        $path = base_path('daily-drop/output.test.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($posts ?? $this->fixture()));

        return $path;
    }

    protected function tearDown(): void
    {
        @unlink(base_path('daily-drop/output.test.json'));
        parent::tearDown();
    }

    public function test_imports_all_types_as_drafts_attributed_to_the_site_author(): void
    {
        Product::create(['name' => 'Anker 737', 'asin' => 'B0ABCDEFGH', 'affiliate_url' => 'https://www.amazon.com/dp/B0ABCDEFGH', 'price' => 149.99]);

        $this->artisan('posts:import', ['file' => $this->writeFixture()])
            ->expectsOutputToContain('2 draft(s) created')
            ->expectsOutputToContain('DRAFTS')
            ->assertSuccessful();

        $review = Post::where('slug', 'anker-737-power-bank-review-review')->first() ?? Post::where('type', 'article')->first();
        $this->assertNotNull($review);
        $this->assertSame('draft', $review->status);
        $this->assertSame($this->author->id, $review->user_id);
        $this->assertEquals(4.4, (float) $review->rating);
        $this->assertNotEmpty($review->pros);
        $this->assertNull($review->source_url);
        $this->assertSame('B0ABCDEFGH', $review->products()->first()?->asin);
        $this->assertSame('anker 737 power bank review', $review->seoMeta->target_query);

        $tip = Post::where('type', 'tech_tip')->first();
        $this->assertNotNull($tip);
        $this->assertSame('draft', $tip->status);
        $this->assertSame('https://www.reddit.com/r/techsupport/comments/example', $tip->source_url);
        $this->assertNull($tip->rating);
        $this->assertNull($tip->pros);
        $this->assertCount(0, $tip->products);
        $this->assertNotNull($tip->seoMeta);
    }

    public function test_category_map_keeps_retired_slugs_from_resurrecting(): void
    {
        $posts = $this->fixture();
        $posts[1]['category_name'] = 'Accessories'; // retired slug, maps to computers

        $this->artisan('posts:import', ['file' => $this->writeFixture($posts)])->assertSuccessful();

        $this->assertNull(Category::where('slug', 'accessories')->first());
        $tip = Post::where('type', 'tech_tip')->first();
        $this->assertSame('computers', $tip->categories()->first()?->slug);
    }

    public function test_tags_are_created_from_tag_names(): void
    {
        $this->artisan('posts:import', ['file' => $this->writeFixture()])->assertSuccessful();

        $this->assertNotNull(Tag::where('slug', 'power-banks')->first());
        $this->assertNotNull(Tag::where('slug', 'windows-11')->first());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('posts:import', ['file' => $this->writeFixture(), '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        $this->assertSame(0, Post::count());
        $this->assertSame(0, Tag::count());
        $this->assertSame(0, Category::count());
    }

    public function test_unknown_type_fails_without_importing(): void
    {
        $posts = $this->fixture();
        $posts[0]['type'] = 'listicle';

        $this->artisan('posts:import', ['file' => $this->writeFixture($posts)])
            ->expectsOutputToContain('Unknown post type')
            ->assertFailed();
    }

    public function test_missing_file_fails_with_guidance(): void
    {
        $this->artisan('posts:import', ['file' => 'daily-drop/does-not-exist.json'])
            ->expectsOutputToContain('daily-drop-build.php')
            ->assertFailed();
    }

    public function test_importer_rejects_unknown_type_directly(): void
    {
        $this->expectExceptionMessage('Unknown post type');

        app(DailyDropImporterService::class)->importOne([
            'title' => 'Bad Type Post',
            'body' => 'Body',
            'type' => 'nope',
        ], $this->author->id);
    }
}
