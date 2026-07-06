<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Support\SocialComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialComposerTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): Post
    {
        static $i = 0;
        $i++;

        return Post::create(array_merge([
            'user_id'      => User::factory()->create()->id,
            'title'        => "Widget Review {$i}",
            'slug'         => "widget-review-{$i}",
            'type'         => 'article',
            'excerpt'      => 'A solid mid-range widget with surprisingly good battery life.',
            'body'         => 'Body text.',
            'status'       => 'draft',
        ], $overrides));
    }

    public function test_bluesky_copy_fits_300_chars_and_links_to_the_review(): void
    {
        $post = $this->makePost([
            'excerpt' => str_repeat('Owners consistently report the battery outlasting the spec sheet. ', 10),
        ]);

        $text = (new SocialComposer())->compose($post, 'bluesky');

        $this->assertLessThanOrEqual(300, Str::length($text));
        $this->assertStringContainsString($post->title, $text);
        $this->assertStringContainsString(route('posts.show', $post->slug), $text);
    }

    public function test_bluesky_copy_survives_a_pathologically_long_title(): void
    {
        $post = $this->makePost(['title' => str_repeat('Ultra Mega Gadget Pro Max ', 20)]);

        $text = (new SocialComposer())->compose($post, 'bluesky');

        $this->assertLessThanOrEqual(300, Str::length($text));
        $this->assertStringEndsWith(route('posts.show', $post->slug), $text);
    }

    public function test_facebook_copy_keeps_the_full_excerpt_and_hashtags(): void
    {
        $post = $this->makePost();

        $text = (new SocialComposer())->compose($post, 'facebook');

        $this->assertStringContainsString($post->title, $text);
        $this->assertStringContainsString($post->excerpt, $text);
        $this->assertStringContainsString(route('posts.show', $post->slug), $text);
        $this->assertStringContainsString('#tech', $text);
    }

    public function test_hashtags_follow_the_post_type(): void
    {
        $tip = $this->makePost(['type' => 'tech_tip']);

        $this->assertStringContainsString('#TechTips', (new SocialComposer())->compose($tip, 'facebook'));
    }

    public function test_links_never_point_at_amazon(): void
    {
        $post = $this->makePost();

        foreach (['bluesky', 'facebook', 'x'] as $platform) {
            $this->assertStringNotContainsString('amazon.', (new SocialComposer())->compose($post, $platform));
        }
    }
}
