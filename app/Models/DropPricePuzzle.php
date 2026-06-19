<?php

namespace App\Models;

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
        'date'          => 'date',
        'locked_at'     => 'datetime',
        'is_preset'     => 'boolean',
        'price'         => 'integer',
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
}
