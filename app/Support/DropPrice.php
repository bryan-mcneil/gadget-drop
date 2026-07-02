<?php

namespace App\Support;

use App\Models\DropPricePuzzle;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side game logic for the homepage "Drop Price" daily game.
 *
 * Responsibilities, kept deliberately separate:
 *  - {@see self::evaluate()} is pure, side-effect-free math that scores a guess.
 *    It returns ORDINAL feedback only (a direction + a closeness band) and NEVER
 *    the dollar distance or percentage, because its result is rendered to the
 *    browser. The secret answer is read server-side at call time and never leaves.
 *  - {@see self::today()} resolves (and caches) the puzzle the homepage should
 *    serve right now.
 *  - {@see self::findPlayable()} resolves a specific archive puzzle by id under
 *    the same playability guard.
 *
 * The hard product constraint: the day's price is the secret answer and must
 * never reach the client before the reveal. Keep it that way — do not add a
 * method here that returns the price to a view, and never log the answer.
 */
class DropPrice
{
    /** A guess more than 25% off the answer is "freezing" (🥶). */
    public const BAND_FREEZING = 0.25;

    /** A guess 10–25% off is "warm" (😊); inside 10% (not exact) is "hot" (🔥). */
    public const BAND_WARM = 0.10;

    /** Guesses allowed before the game is lost. */
    public const MAX_GUESSES = 5;

    /**
     * Score a single guess against the secret answer.
     *
     * Returns ordinal feedback only — the band is a bucket, never the raw
     * percentage or dollar gap — so the caller can safely surface it to the
     * browser without narrowing the answer. The `direction` points the player
     * toward the answer ("higher" = the answer is above your guess).
     *
     * @return array{direction: 'higher'|'lower'|'equal', band: 'freezing'|'warm'|'hot'|'nailed', won: bool}
     */
    public static function evaluate(int $guess, int $answer): array
    {
        if ($guess === $answer) {
            return ['direction' => 'equal', 'band' => 'nailed', 'won' => true];
        }

        $direction = $guess < $answer ? 'higher' : 'lower';

        // Defensive: locked puzzles always have price > 0, but never divide by a
        // zero/negative answer — treat it as maximally cold rather than throwing.
        $pct = $answer > 0 ? abs($guess - $answer) / $answer : 1.0;

        $band = match (true) {
            $pct > self::BAND_FREEZING => 'freezing',
            $pct >= self::BAND_WARM    => 'warm',
            default                    => 'hot',
        };

        return ['direction' => $direction, 'band' => $band, 'won' => false];
    }

    /**
     * The puzzle the homepage should serve right now: today's locked puzzle, or
     * the most recent prior one while the hourly cron has not yet locked today's
     * (the homepage must never go blank in that gap).
     *
     * Cached on the database store (no Redis) until the end of the UTC day so it
     * naturally re-resolves at the day boundary; the lock command also calls
     * Cache::forget('dropprice.today') the moment a new puzzle is locked. Wrapped
     * in try/catch so a misconfigured cache layer degrades to a live DB read
     * rather than blanking the game.
     */
    public static function today(): ?DropPricePuzzle
    {
        try {
            return Cache::remember('dropprice.today', now()->endOfDay(), fn () => self::resolveToday());
        } catch (\Throwable) {
            return self::resolveToday();
        }
    }

    /**
     * The latest locked puzzle dated today or earlier. Unlocked future presets
     * (no number, no locked_at) are excluded, and a defensively future-dated row
     * can never be served as "today".
     */
    private static function resolveToday(): ?DropPricePuzzle
    {
        return DropPricePuzzle::query()
            ->playable()
            ->orderByDesc('date')
            ->first();
    }

    /**
     * A specific playable puzzle by primary key — the same guard as today()
     * (locked, not future-dated), via the shared DropPricePuzzle::playable()
     * scope. Used by the Livewire component to resolve an archive puzzle from
     * a client-supplied id — never trust that id blindly; an unlocked/queued
     * preset id resolves to null here, exactly like a tampered id would for
     * today().
     *
     * Deliberately uncached (unlike today()): archive traffic fans out across
     * many distinct ids, so a per-id cache entry buys little for a single
     * indexed primary-key lookup.
     */
    public static function findPlayable(int $id): ?DropPricePuzzle
    {
        return DropPricePuzzle::query()->playable()->find($id);
    }
}
