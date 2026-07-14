<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\WorthItVote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorthItVoteTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        return Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Gadget Review',
            'slug' => 'gadget-review-'.uniqid(),
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_the_same_voter_cannot_vote_twice_on_a_post(): void
    {
        $post = $this->makePost();
        $hash = str_repeat('a', 64);

        WorthItVote::create(['post_id' => $post->id, 'choice' => 'worth', 'voter_hash' => $hash]);

        $this->expectException(QueryException::class);

        WorthItVote::create(['post_id' => $post->id, 'choice' => 'skip', 'voter_hash' => $hash]);
    }

    public function test_the_same_voter_can_vote_on_different_posts(): void
    {
        $a = $this->makePost();
        $b = $this->makePost();
        $hash = str_repeat('b', 64);

        WorthItVote::create(['post_id' => $a->id, 'choice' => 'worth', 'voter_hash' => $hash]);
        WorthItVote::create(['post_id' => $b->id, 'choice' => 'worth', 'voter_hash' => $hash]);

        $this->assertSame(2, WorthItVote::where('voter_hash', $hash)->count());
    }

    public function test_summary_hides_percentage_below_the_gate(): void
    {
        $post = $this->makePost();
        // 4 votes total — under MIN_VOTES_FOR_PCT (5).
        WorthItVote::factory()->count(3)->worth()->create(['post_id' => $post->id]);
        WorthItVote::factory()->skip()->create(['post_id' => $post->id]);

        $summary = $post->worthItSummary();

        $this->assertSame(3, $summary['worth']);
        $this->assertSame(1, $summary['skip']);
        $this->assertSame(4, $summary['total']);
        $this->assertNull($summary['pct']);
    }

    public function test_summary_reveals_percentage_at_the_gate(): void
    {
        $post = $this->makePost();
        // 4 worth + 1 skip = 5 total → 80%.
        WorthItVote::factory()->count(4)->worth()->create(['post_id' => $post->id]);
        WorthItVote::factory()->skip()->create(['post_id' => $post->id]);

        $summary = $post->worthItSummary();

        $this->assertSame(5, $summary['total']);
        $this->assertSame(80, $summary['pct']);
    }

    public function test_summarize_gates_and_rounds_to_the_nearest_percent(): void
    {
        // Under the 5-vote gate: pct stays null regardless of ratio.
        $this->assertNull(WorthItVote::summarize(2, 2)['pct']);

        // At/above the gate: rounded to the nearest whole percent.
        $this->assertSame(60, WorthItVote::summarize(3, 2)['pct']);   // 5 total
        $this->assertSame(33, WorthItVote::summarize(2, 4)['pct']);   // 33.33% → 33
        $this->assertSame(0, WorthItVote::summarize(0, 5)['pct']);    // all skip
        $this->assertSame(100, WorthItVote::summarize(6, 0)['pct']);  // all worth
    }

    public function test_votes_cascade_when_the_post_is_deleted(): void
    {
        $post = $this->makePost();
        WorthItVote::factory()->count(3)->create(['post_id' => $post->id]);

        $this->assertSame(3, WorthItVote::count());

        $post->delete();

        $this->assertSame(0, WorthItVote::count());
    }

    public function test_factory_states_set_each_choice(): void
    {
        $post = $this->makePost();

        $this->assertSame('worth', WorthItVote::factory()->worth()->create(['post_id' => $post->id])->choice);
        $this->assertSame('skip', WorthItVote::factory()->skip()->create(['post_id' => $post->id])->choice);
    }

    public function test_factory_is_self_sufficient_without_a_post_id(): void
    {
        // The lazy default mints its own published article — a bare create() works
        // and produces a 64-char identity, so the default path can't silently rot.
        $vote = WorthItVote::factory()->create();

        $this->assertNotNull($vote->post_id);
        $this->assertDatabaseHas('posts', ['id' => $vote->post_id]);
        $this->assertSame(64, strlen($vote->voter_hash));
    }
}
