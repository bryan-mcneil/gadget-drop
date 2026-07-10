<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DropPricePuzzle extends Model
{
    protected $fillable = [
        'puzzle_number',
        'date',
        'product_id',
        'price',
        'product_name',
        'product_image_url',
        'affiliate_product_id',
        'locked_at',
        'is_preset',
    ];

    protected $casts = [
        'date' => 'date',
        'locked_at' => 'datetime',
        'is_preset' => 'boolean',
        'price' => 'integer',
        'puzzle_number' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(DropPriceResult::class);
    }

    /**
     * Rows the public may ever be served: locked, and dated today or earlier.
     * An unlocked queued preset or a future-dated row must never surface —
     * their price is still a secret answer for a day that hasn't happened.
     * The single source of this rule for queries; {@see self::isPlayable()}
     * is its in-memory twin — keep the two in sync.
     */
    public function scopePlayable(Builder $query): Builder
    {
        return $query
            ->whereNotNull('locked_at')
            ->whereDate('date', '<=', now()->toDateString());
    }

    /** In-memory twin of {@see self::scopePlayable()} for an already-loaded row. */
    public function isPlayable(): bool
    {
        return $this->locked_at !== null && $this->date->lte(today());
    }
}
