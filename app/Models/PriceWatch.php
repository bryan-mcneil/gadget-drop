<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PriceWatch extends Model
{
    use Prunable;

    // Only signup-supplied + server-set inputs are mass-assignable. expires_at and
    // token are derived in booted(); verified_at/notified_at/closing_mail_sent_at are
    // state transitions set explicitly, never from client input (mirrors Subscriber —
    // this is what stops a bot POSTing verified_at to skip the email round-trip).
    protected $fillable = [
        'product_id', 'email', 'purchase_price', 'purchased_at', 'token', 'ip_address',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'purchased_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
        'notified_at' => 'datetime',
        'closing_mail_sent_at' => 'datetime',
    ];

    /**
     * Fill the magic-link token and the return-window expiry on insert so every
     * caller (signup component, tests, future importers) gets them for free.
     *
     * config/watch.php arrives in Phase 3.3 as the single source for the window;
     * the 30-day fallback keeps this model self-contained until then.
     */
    protected static function booted(): void
    {
        static::creating(function (PriceWatch $watch): void {
            if (empty($watch->token)) {
                $watch->token = (string) Str::uuid();
            }

            if (empty($watch->expires_at) && ! empty($watch->purchased_at)) {
                $watch->expires_at = $watch->purchased_at
                    ->copy()
                    ->addDays((int) config('watch.window_days', 30));
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Verified, still inside the return window, and not yet alerted. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at')
            ->whereNull('notified_at')
            ->whereDate('expires_at', '>=', today());
    }

    /** Past the return window (the window's last day still counts as active). */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereDate('expires_at', '<', today());
    }

    /** Dollars a return-and-rebuy at $current would save; never negative. */
    public function savings(float $current): float
    {
        return max(0, (float) $this->purchase_price - $current);
    }

    /**
     * The product's most recent published review — where watch mails send
     * readers (never an /out affiliate link: email → out would skew click
     * attribution, and the review page holds the disclosure context).
     */
    public function reviewUrl(): string
    {
        $post = $this->product?->posts()
            ->published()
            ->latest('published_at')
            ->first(['posts.id', 'posts.slug']);

        return $post ? url('/posts/'.$post->slug) : route('deals');
    }

    /** Auto-pruned 60 days after the return window closes (light-PII hygiene). */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDays(60));
    }
}
