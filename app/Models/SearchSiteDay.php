<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (engine, day, search type) — the site-level clicks/impressions
 * trend. CTR is always computed from clicks/impressions, never stored.
 */
class SearchSiteDay extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date'        => 'date',
        'clicks'      => 'integer',
        'impressions' => 'integer',
        'position'    => 'decimal:2',
    ];
}
