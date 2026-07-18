<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single one-tap "worth it" / "I'd skip" vote on a post. Identity is a salted
 * hash (no accounts, no raw IP); the unique index on (post_id, voter_hash) is the
 * double-vote gate. See docs/plans/02-worth-it-voting.md.
 */
class WorthItVote extends Model
{
    use HasFactory;

    /** The two allowed choices, stored verbatim in the `choice` string column. */
    public const CHOICE_WORTH = 'worth';

    public const CHOICE_SKIP = 'skip';

    /** Percentages stay hidden until a post clears this many total votes. */
    public const MIN_VOTES_FOR_PCT = 5;

    protected $fillable = ['post_id', 'choice', 'voter_hash'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @param  Builder<WorthItVote>  $query */
    public function scopeWorth(Builder $query): Builder
    {
        return $query->where('choice', self::CHOICE_WORTH);
    }

    /** @param  Builder<WorthItVote>  $query */
    public function scopeSkip(Builder $query): Builder
    {
        return $query->where('choice', self::CHOICE_SKIP);
    }

    /**
     * Turn a worth/skip tally into a display summary, applying the small-sample
     * honesty gate: `pct` is null until MIN_VOTES_FOR_PCT total votes. This is the
     * ONE place the gate lives, so neither the post page nor /deals can fudge it
     * (same brand rule as PriceIntel's honesty gates).
     *
     * @return array{worth: int, skip: int, total: int, pct: ?int}
     */
    public static function summarize(int $worth, int $skip): array
    {
        $total = $worth + $skip;

        return [
            'worth' => $worth,
            'skip' => $skip,
            'total' => $total,
            'pct' => $total >= self::MIN_VOTES_FOR_PCT
                ? (int) round($worth / $total * 100)
                : null,
        ];
    }
}
