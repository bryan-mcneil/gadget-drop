<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropPriceResult extends Model
{
    protected $fillable = [
        'drop_price_puzzle_id',
        'subscriber_id',
        'won',
        'guesses_used',
        'closest_miss_pct',
        'played_on',
    ];

    protected $casts = [
        'won' => 'boolean',
        'guesses_used' => 'integer',
        'closest_miss_pct' => 'integer',
        'played_on' => 'date',
    ];

    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(DropPricePuzzle::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}
