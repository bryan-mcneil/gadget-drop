<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagClaimsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): Post
    {
        static $i = 0;
        $i++;

        return Post::create(array_merge([
            'user_id'      => User::factory()->create()->id,
            'title'        => "Clean Post {$i}",
            'slug'         => "clean-post-{$i}",
            'type'         => 'article',
            'body'         => 'Verified-purchase owners consistently report solid battery life.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_clean_content_passes(): void
    {
        $this->makePost();

        $this->artisan('content:flag-claims')
            ->expectsOutputToContain('Clean')
            ->assertSuccessful();
    }

    public function test_testing_claims_are_flagged_with_the_offending_phrase(): void
    {
        $this->makePost();
        $offender = $this->makePost([
            'title' => 'Widget Review',
            'slug'  => 'widget-review',
            'body'  => 'We tested this widget for a month and our measurements show 12 hours of battery.',
        ]);

        $this->artisan('content:flag-claims')
            ->expectsOutputToContain('we tested')
            ->expectsOutputToContain("/admin/posts/{$offender->id}/edit")
            ->assertFailed();
    }

    public function test_drafts_are_ignored_unless_all_flag_is_passed(): void
    {
        $this->makePost([
            'status' => 'draft',
            'body'   => 'In our lab this thing caught fire.',
        ]);

        $this->artisan('content:flag-claims')->assertSuccessful();
        $this->artisan('content:flag-claims --all')->assertFailed();
    }
}
