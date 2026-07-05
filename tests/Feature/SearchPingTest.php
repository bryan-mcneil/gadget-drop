<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\SearchSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchPingTest extends TestCase
{
    use RefreshDatabase;

    private function publish(array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Ping Widget Review',
            'slug'         => 'ping-widget-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subMinute(),
        ], $overrides));
    }

    public function test_publishing_pings_indexnow_and_logs_the_submission(): void
    {
        config(['search.ping_enabled' => true, 'search.indexnow_key' => 'abc123']);
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $post = $this->publish();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.indexnow.org')
            && in_array(route('posts.show', $post->slug), $r['urlList'], true));

        $this->assertDatabaseHas('search_submissions', [
            'url'     => route('posts.show', $post->slug),
            'engine'  => 'indexnow',
            'trigger' => 'publish',
        ]);
    }

    public function test_no_ping_when_disabled(): void
    {
        // ping_enabled defaults to false in the test environment.
        Http::fake();

        $this->publish();

        Http::assertNothingSent();
        $this->assertDatabaseCount('search_submissions', 0);
    }

    public function test_draft_import_does_not_ping(): void
    {
        config(['search.ping_enabled' => true, 'search.indexnow_key' => 'abc123']);
        Http::fake();

        $this->publish(['status' => 'draft', 'published_at' => null]);

        Http::assertNothingSent();
    }

    public function test_slug_change_reannounces_old_and_new_urls(): void
    {
        config(['search.ping_enabled' => true, 'search.indexnow_key' => 'abc123']);
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $post = $this->publish(['slug' => 'old-slug']);
        SearchSubmission::query()->delete(); // isolate the slug-change ping

        $post->update(['slug' => 'new-slug']);

        $this->assertDatabaseHas('search_submissions', ['url' => route('posts.show', 'old-slug'), 'trigger' => 'update']);
        $this->assertDatabaseHas('search_submissions', ['url' => route('posts.show', 'new-slug'), 'trigger' => 'update']);
    }

    public function test_indexnow_txt_serves_the_key_or_404s(): void
    {
        config(['search.indexnow_key' => 'abc123def456']);
        $this->get('/indexnow.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('abc123def456');

        config(['search.indexnow_key' => null]);
        $this->get('/indexnow.txt')->assertNotFound();
    }

    public function test_llms_txt_maps_the_site(): void
    {
        $category = Category::firstOrCreate(['slug' => 'audio'], ['name' => 'Audio & Home Theater']);
        $post = $this->publish(['title' => 'The Best Headphones', 'slug' => 'the-best-headphones']);
        $post->categories()->attach($category->id);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('# GadgetDrop')
            ->assertSee(route('deals'))
            ->assertSee('The Best Headphones');
    }
}
