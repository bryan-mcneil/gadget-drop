<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per ASIN ever seen by market:import — the wide, uncurated price
 * layer (docs/MARKET-IMPORT.md), separate from the review-backed products
 * table. No observer: MarketImportService writes change-only snapshots
 * explicitly, and last_seen_at records same-price sightings.
 */
class MarketProduct extends Model
{
    protected $fillable = [
        'asin', 'title', 'description', 'brand', 'category', 'url', 'current_price',
        'list_price', 'rating', 'review_count', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'list_price' => 'decimal:2',
        'rating' => 'decimal:1',
        'review_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(MarketPriceSnapshot::class);
    }
}
