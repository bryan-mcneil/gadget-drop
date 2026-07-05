<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\SearchOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoAdminTest extends TestCase
{
    use RefreshDatabase;

    private function opportunity(array $overrides = []): SearchOpportunity
    {
        return SearchOpportunity::create(array_merge([
            'kind'          => 'content_gap',
            'query'         => 'best budget anc headphones',
            'query_hash'    => sha1('best budget anc headphones'),
            'score'         => 42,
            'status'        => 'open',
            'evidence'      => ['impressions' => 200, 'phrasings' => [['query' => 'best budget anc headphones']]],
            'first_seen_at' => now(),
            'last_seen_at'  => now(),
        ], $overrides));
    }

    public function test_the_dashboard_requires_auth(): void
    {
        $this->get('/admin/seo')->assertRedirect('/login');
    }

    public function test_index_renders_with_opportunities(): void
    {
        $this->opportunity();

        $this->actingAs(User::factory()->create())
            ->get('/admin/seo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Seo/Index')
                ->has('opportunities', 1)
                ->where('opportunities.0.kind', 'content_gap')
                ->has('trend.lines', 3)
                ->has('coverage')
                ->has('submissions')
            );
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $this->opportunity(); // open
        $this->opportunity(['kind' => 'rising', 'query' => 'x', 'query_hash' => sha1('x'), 'status' => 'done']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/seo?status=done')
            ->assertInertia(fn (Assert $page) => $page
                ->has('opportunities', 1)
                ->where('opportunities.0.status', 'done')
            );
    }

    public function test_an_opportunity_can_be_dismissed(): void
    {
        $opp = $this->opportunity();

        $this->actingAs(User::factory()->create())
            ->post("/admin/seo/opportunities/{$opp->id}", ['status' => 'dismissed'])
            ->assertRedirect();

        $this->assertSame('dismissed', $opp->fresh()->status);
    }

    public function test_reping_action_redirects(): void
    {
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Reping Review',
            'slug'         => 'reping-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/admin/seo/posts/{$post->id}/reping")
            ->assertRedirect();
    }
}
