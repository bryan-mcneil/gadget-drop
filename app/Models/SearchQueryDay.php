<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Query x page / day (Google web only) — the raw grain the opportunity miner
 * and the expected-CTR curve are built from.
 */
class SearchQueryDay extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'position' => 'decimal:2',
    ];
}
