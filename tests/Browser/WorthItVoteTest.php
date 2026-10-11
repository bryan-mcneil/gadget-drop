<?php

namespace Tests\Browser;

use App\Models\Post;
use App\Models\User;
use App\Models\WorthItVote;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser coverage for the Worth-It vote flow. Local run (two terminals from the
 * project root): `php artisan serve` in one, `php artisan dusk` in the other —
 * see tests/Browser/SmokeTest.php for the full env-swap explanation. Uses the
 * sqlite FILE database (database/dusk.sqlite) + DatabaseTruncation; never MySQL.
 */
class WorthItVoteTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** Seed a published review, this suite's Model::create convention (no factory). */
    private function review(string $title): Post
    {
        return Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'slug' => str_replace(' ', '-', strtolower($title)),
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_a_visitor_can_vote_and_the_result_persists_across_a_reload(): void
    {
        $post = $this->review('Dusk Vote Widget');

        $this->browse(function (Browser $browser) use ($post) {
            $browser->visit('/posts/'.$post->slug)
                ->assertSee('Was this worth it?')
                // Livewire vote — the result state replaces the ballot with no reload.
                // waitForLivewire gates on hydration so the click can't race the wire.
                ->waitForLivewire(fn (Browser $b) => $b->click('button[aria-label="Vote: worth it"]'))
                ->waitForText('Thanks for voting!')
                ->assertDontSee('Was this worth it?')
                // A full reload keeps the result (identity is the session-derived hash).
                ->refresh()
                ->waitForText('Thanks for voting!')
                ->assertDontSee('Was this worth it?');
        });

        $this->assertSame(1, WorthItVote::where('post_id', $post->id)->where('choice', 'worth')->count());
    }

    public function test_a_separate_browser_session_votes_independently(): void
    {
        $post = $this->review('Dusk Independent Widget');

        $this->browse(function (Browser $first, Browser $second) use ($post) {
            // First visitor votes "worth it".
            $first->visit('/posts/'.$post->slug)
                ->waitForLivewire(fn (Browser $b) => $b->click('button[aria-label="Vote: worth it"]'))
                ->waitForText('Thanks for voting!');

            // A fresh browser (new session cookie → new hash) still gets a ballot,
            // and its vote is counted independently.
            $second->visit('/posts/'.$post->slug)
                ->assertSee('Was this worth it?')
                ->waitForLivewire(fn (Browser $b) => $b->click('button[aria-label="Vote: I would skip it"]'))
                ->waitForText('Thanks for voting!');
        });

        $this->assertSame(1, WorthItVote::where('post_id', $post->id)->where('choice', 'worth')->count());
        $this->assertSame(1, WorthItVote::where('post_id', $post->id)->where('choice', 'skip')->count());
    }
}
