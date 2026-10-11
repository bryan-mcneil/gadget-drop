<?php

namespace Tests\Feature\Livewire;

use App\Livewire\WorthItVote;
use App\Models\Post;
use App\Models\User;
use App\Models\WorthItVote as Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class WorthItVoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The limiter is cache-backed and can leak across tests in one process.
        RateLimiter::clear('worth-it:127.0.0.1');
    }

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

    public function test_it_renders_the_ballot_for_a_first_time_visitor(): void
    {
        $post = $this->makePost();

        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->assertSet('voted', null)
            ->assertSee('Was this worth it?')
            ->assertSee('Worth it')
            ->assertSee("I'd skip", false);
    }

    public function test_a_vote_records_a_row_with_a_hashed_identity(): void
    {
        $post = $this->makePost();

        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->call('vote', 'worth')
            ->assertSet('voted', 'worth');

        $row = Vote::where('post_id', $post->id)->sole();
        $this->assertSame('worth', $row->choice);
        // Salted hash, never a raw IP.
        $this->assertSame(64, strlen($row->voter_hash));
        $this->assertStringNotContainsString('127.0.0.1', $row->voter_hash);
    }

    public function test_a_second_vote_from_the_same_visitor_does_not_duplicate(): void
    {
        $post = $this->makePost();

        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->call('vote', 'worth')
            ->assertSet('voted', 'worth')
            ->call('vote', 'skip')          // attempt to switch
            ->assertSet('voted', 'worth');  // original choice preserved

        $this->assertSame(1, Vote::where('post_id', $post->id)->count());
        $this->assertSame('worth', Vote::where('post_id', $post->id)->value('choice'));
    }

    public function test_the_recorded_choice_is_preloaded_on_a_fresh_mount(): void
    {
        $post = $this->makePost();

        Livewire::test(WorthItVote::class, ['postId' => $post->id])->call('vote', 'skip');

        // A page refresh (new mount, same session hash) opens in the result state.
        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->assertSet('voted', 'skip');
    }

    public function test_an_invalid_choice_is_ignored(): void
    {
        $post = $this->makePost();

        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->call('vote', 'maybe')
            ->assertSet('voted', null);

        $this->assertSame(0, Vote::count());
    }

    public function test_the_rate_limiter_silently_blocks_a_flood(): void
    {
        $post = $this->makePost();

        // Exhaust the 10/min budget for this IP.
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('worth-it:127.0.0.1', 60);
        }

        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->call('vote', 'worth')
            ->assertSet('voted', null);   // no error thrown, just no vote

        $this->assertSame(0, Vote::where('post_id', $post->id)->count());
    }

    public function test_the_result_shows_a_percentage_once_the_gate_is_cleared(): void
    {
        $post = $this->makePost();
        // 4 worth + 1 skip already recorded (5 total, gate cleared).
        Vote::factory()->count(4)->worth()->create(['post_id' => $post->id]);
        Vote::factory()->skip()->create(['post_id' => $post->id]);

        // Visitor casts the 6th vote → 5 worth / 1 skip → round(5/6) = 83%.
        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->call('vote', 'worth')
            ->assertSet('voted', 'worth')
            ->assertSee('83%')
            ->assertSee("readers say it's worth it", false);
    }

    public function test_the_result_shows_the_early_line_below_the_gate(): void
    {
        $post = $this->makePost();
        // 3 votes recorded; the visitor's makes 4 — still under the 5-vote gate.
        Vote::factory()->count(3)->worth()->create(['post_id' => $post->id]);

        Livewire::test(WorthItVote::class, ['postId' => $post->id])
            ->call('vote', 'skip')
            ->assertSet('voted', 'skip')
            ->assertSee('Early votes', false)
            ->assertDontSee('%');
    }
}
