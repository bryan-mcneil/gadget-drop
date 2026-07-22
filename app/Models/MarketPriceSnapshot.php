<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * created_at is fillable on purpose: import rows carry the scrape timestamp,
 * so snapshots are recorded at observation time, not import time.
 */
class MarketPriceSnapshot extends Model
{
    protected $fillable = ['market_product_id', 'price', 'created_at'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketProduct::class, 'market_product_id');
    }
}
