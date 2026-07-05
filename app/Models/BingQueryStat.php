<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A weekly snapshot of Bing's rolling ~6-month per-query aggregate. Bing's API
 * exposes no date range, so captured_on models when we took the reading.
 */
class BingQueryStat extends Model
{
    protected $guarded = [];

    protected $casts = [
        'captured_on'             => 'date',
        'clicks'                  => 'integer',
        'impressions'             => 'integer',
        'avg_click_position'      => 'decimal:2',
        'avg_impression_position' => 'decimal:2',
    ];
}
