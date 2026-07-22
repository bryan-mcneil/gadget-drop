<?php

namespace App\Livewire;

use App\Mail\WatchVerifyMail;
use App\Models\PriceWatch;
use App\Models\Product;
use App\Support\PriceIntel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Already bought it?" post-purchase price watch signup, rendered under the
 * tracked-price panel on review pages. Email + purchase date only — the
 * purchase baseline is OUR tracked price on that date (PriceIntel::priceOn),
 * never a reader-typed number, so every claim a later mail makes traces back
 * to snapshot data. See docs/plans/03-post-purchase-watch.md §3.2.
 */
class PriceWatchSignup extends Component
{
    /** Locked so the mounted product can't be swapped by a tampered payload. */
    #[Locked]
    public int $productId;

    public string $email = '';

    /** Y-m-d purchase date; defaults to today, bounded to the return window. */
    public string $purchased_on = '';

    /** Honeypot — hidden from humans; anything here means a bot filled the form. */
    public string $company = '';

    /** Unix time the form was first rendered; sub-3-second submits are bots. */
    #[Locked]
    public int $renderedAt = 0;

    /** null | 'pending' | 'duplicate' | 'throttled' | 'error' */
    public ?string $status = null;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
        $this->purchased_on = today()->toDateString();

        // now()->timestamp (not time()) so tests can time-travel past the gate.
        $this->renderedAt = now()->timestamp;
    }

    /** rules() (not #[Validate]) because the date bounds are computed per-request. */
    protected function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            // An already-closed window is pointless — no older than window_days.
            'purchased_on' => 'required|date|before_or_equal:today|after_or_equal:'
                .today()->subDays((int) config('watch.window_days', 30))->toDateString(),
        ];
    }

    public function startWatch(): void
    {
        $this->status = null;
        $this->validate();

        // Bots get a silent "success" — no watch, no error to learn from.
        if ($this->company !== '' || (now()->timestamp - $this->renderedAt) < 3) {
            $this->reset('email', 'company');
            $this->status = 'pending';

            return;
        }

        $key = 'watch-signup:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->status = 'throttled';

            return;
        }

        $product = Product::find($this->productId);

        if (! $product || $product->price === null) {
            $this->status = 'error';

            return;
        }

        // Same reader, any casing of the same address.
        $email = Str::lower($this->email);

        // One live watch per reader per product: anything unexpired and not yet
        // alerted counts, verified or not (an unverified duplicate would just
        // mint a second verify mail for the same promise).
        $alreadyWatching = PriceWatch::query()
            ->where('product_id', $product->id)
            ->where('email', $email)
            ->whereNull('notified_at')
            ->whereDate('expires_at', '>=', today())
            ->exists();

        if ($alreadyWatching) {
            $this->status = 'duplicate';

            return;
        }

        $purchasedOn = Carbon::parse($this->purchased_on)->startOfDay();

        try {
            $watch = PriceWatch::create([
                'product_id' => $product->id,
                'email' => $email,
                'purchase_price' => PriceIntel::priceOn($product->id, $purchasedOn),
                'purchased_at' => $purchasedOn,
                'ip_address' => request()->ip(),
            ]);

            Mail::to($watch->email)->send(new WatchVerifyMail($watch));

            RateLimiter::hit($key, 3600);
            $this->reset('email', 'company');
            $this->status = 'pending';
        } catch (\Throwable $e) {
            report($e);
            $this->status = 'error';
        }
    }

    public function render()
    {
        return view('livewire.price-watch-signup', [
            'windowDays' => (int) config('watch.window_days', 30),
        ]);
    }
}
