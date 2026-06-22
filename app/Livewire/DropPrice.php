<?php

namespace App\Livewire;

use App\Models\DropPriceResult;
use App\Models\Subscriber;
use App\Support\DropPrice as DropPriceGame;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The interactive "Drop Price" homepage game (the secrecy boundary).
 *
 * The day's price is the secret answer. It is NEVER a public property and is
 * never rendered before the game ends — it is read on demand from
 * {@see DropPriceGame::today()} inside the server-side actions, scored to an
 * ordinal band, and only surfaced via {@see self::$revealPrice} once the game
 * is finished. Public properties serialize into the Livewire snapshot that ships
 * to the browser, so anything that narrows the answer must stay out of them.
 */
class DropPrice extends Component
{
    /** Display-only puzzle facts (safe to ship — they never narrow the price). */
    public int $puzzleNumber;

    public string $productName;

    public string $productImage;

    /** The current guess being typed (client-visible — it's the player's own input). */
    public ?int $guess = null;

    /**
     * Ordinal feedback for each guess so far. Each row is
     * ['guess' => int, 'direction' => string, 'band' => string] — the player's
     * own guess plus the band/direction. NEVER the price, distance, or pct.
     *
     * @var array<int, array{guess: int, direction: string, band: string}>
     */
    public array $results = [];

    public bool $finished = false;

    public bool $won = false;

    /**
     * Reveal-only fields, null until the game is finished. Setting these is the
     * single point at which the answer becomes visible to the browser.
     */
    public ?int $revealPrice = null;

    public ?int $affiliateProductId = null;

    /** Email capture (mirrors {@see JoinTheDrop}). */
    #[Validate('required|email|max:255')]
    public string $email = '';

    /** null | 'success' | 'duplicate' | 'error' */
    public ?string $saveStatus = null;

    public function mount(int $number, string $name, string $image): void
    {
        $this->puzzleNumber = $number;
        $this->productName  = $name;
        $this->productImage = $image;
    }

    public function guess(): void
    {
        // No-op once the game is over or all guesses are spent.
        if ($this->finished || count($this->results) >= DropPriceGame::MAX_GUESSES) {
            return;
        }

        $this->validate(['guess' => 'required|integer|min:1|max:100000']);

        $puzzle = DropPriceGame::today();

        // Defensive: if the puzzle vanished mid-session there is nothing to score.
        if ($puzzle === null) {
            return;
        }

        $result = DropPriceGame::evaluate($this->guess, (int) $puzzle->price);

        $this->results[] = [
            'guess'     => $this->guess,
            'direction' => $result['direction'],
            'band'      => $result['band'],
        ];

        if ($result['won']) {
            $this->won = true;
            $this->finished = true;
        } elseif (count($this->results) >= DropPriceGame::MAX_GUESSES) {
            $this->finished = true;
        }

        if ($this->finished) {
            // Reveal happens here and only here.
            $this->revealPrice = (int) $puzzle->price;
            $this->affiliateProductId = $puzzle->affiliate_product_id;

            // Hand Alpine ordinal-only data for localStorage streaks + share.
            $this->dispatch(
                'dropprice-finished',
                won: $this->won,
                number: $this->puzzleNumber,
                guesses: count($this->results),
                results: $this->results,
            );
        }

        $this->guess = null;
    }

    /**
     * Save the player's email (= newsletter subscribe, mirrors {@see JoinTheDrop})
     * and, if they have finished today's puzzle, sync their streak snapshot and
     * upsert today's result row.
     *
     * @param array<string, mixed> $stats The client's localStorage streak counters
     *                                     (advisory bragging stats, sanitized + merged).
     */
    public function save(array $stats = []): void
    {
        $this->saveStatus = null;
        $this->validate(['email' => 'required|email|max:255']);

        try {
            $subscriber = Subscriber::firstOrCreate(
                ['email' => $this->email],
                ['token' => (string) Str::uuid(), 'ip_address' => request()->ip()],
            );

            $this->saveStatus = $subscriber->wasRecentlyCreated ? 'success' : 'duplicate';

            $this->syncStreak($subscriber, $stats);

            if ($this->saveStatus === 'success') {
                $this->email = '';
            }
        } catch (\Throwable $e) {
            $this->saveStatus = 'error';
        }
    }

    /**
     * Merge the client's streak counters onto the subscriber (bests/totals taken
     * as max, current streaks taken from the client) and upsert today's result
     * row from server-side game state. The result row's win/guesses/closest-miss
     * are computed on the server (source of truth), not trusted from the client.
     */
    private function syncStreak(Subscriber $subscriber, array $stats): void
    {
        $int = static fn ($v): int => max(0, (int) ($v ?? 0));

        // Streak columns are intentionally NOT mass-assignable (see Subscriber),
        // so forceFill writes them deliberately rather than from a fillable bag.
        $subscriber->forceFill([
            // Current streaks are advisory — accept the client's value.
            'play_streak'      => $int($stats['playStreak'] ?? $subscriber->play_streak),
            'win_streak'       => $int($stats['winStreak'] ?? $subscriber->win_streak),
            // Bests + totals never regress.
            'best_play_streak' => max((int) $subscriber->best_play_streak, $int($stats['bestPlayStreak'] ?? 0)),
            'best_win_streak'  => max((int) $subscriber->best_win_streak, $int($stats['bestWinStreak'] ?? 0)),
            'total_plays'      => max((int) $subscriber->total_plays, $int($stats['totalPlays'] ?? 0)),
            'total_wins'       => max((int) $subscriber->total_wins, $int($stats['totalWins'] ?? 0)),
            'last_played_on'   => now()->toDateString(),
        ])->save();

        if (! $this->finished) {
            return;
        }

        $puzzle = DropPriceGame::today();

        if ($puzzle === null) {
            return;
        }

        DropPriceResult::updateOrCreate(
            ['subscriber_id' => $subscriber->id, 'drop_price_puzzle_id' => $puzzle->id],
            [
                'won'              => $this->won,
                'guesses_used'     => count($this->results),
                'closest_miss_pct' => $this->closestMissPct((int) $puzzle->price),
                'played_on'        => $puzzle->date->toDateString(),
            ],
        );
    }

    /**
     * Smallest percentage (×100) any guess was off the answer; null on a win.
     * Computed server-side from the player's own stored guesses — never shipped
     * to the browser as a live hint.
     */
    private function closestMissPct(int $answer): ?int
    {
        if ($this->won || $answer <= 0 || $this->results === []) {
            return null;
        }

        $closest = min(array_map(
            fn (array $r): float => abs($r['guess'] - $answer) / $answer,
            $this->results,
        ));

        return (int) round($closest * 100);
    }

    public function render()
    {
        return view('livewire.drop-price');
    }
}
