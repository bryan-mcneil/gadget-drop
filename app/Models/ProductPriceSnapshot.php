<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceSnapshot extends Model
{
    /**
     * created_at is fillable on purpose: the backfill command writes historical
     * snapshots (e.g. from drop_price_puzzles) at their original dates.
     */
    protected $fillable = ['product_id', 'price', 'source', 'created_at'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
