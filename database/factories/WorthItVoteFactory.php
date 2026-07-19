<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Models\WorthItVote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorthItVote>
 */
class WorthItVoteFactory extends Factory
{
    protected $model = WorthItVote::class;

    public function definition(): array
    {
        return [
            // Self-sufficient default: mint a minimal published review when the
            // caller doesn't supply a post_id. Tests usually pass one explicitly,
            // in which case this closure is never evaluated (overridden attribute).
            'post_id' => function () {
                $title = 'Voteable '.Str::random(6);

                return Post::create([
                    'user_id' => User::factory()->create()->id,
                    'title' => $title,
                    'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
                    'type' => 'article',
                    'body' => 'Body.',
                    'status' => 'published',
                    'published_at' => now()->subDay(),
                ])->id;
            },
            'choice' => fake()->randomElement([WorthItVote::CHOICE_WORTH, WorthItVote::CHOICE_SKIP]),
            // 64 hex chars — same shape as hash_hmac('sha256', …) produces.
            'voter_hash' => hash('sha256', (string) Str::uuid()),
        ];
    }

    public function worth(): static
    {
        return $this->state(['choice' => WorthItVote::CHOICE_WORTH]);
    }

    public function skip(): static
    {
        return $this->state(['choice' => WorthItVote::CHOICE_SKIP]);
    }
}
