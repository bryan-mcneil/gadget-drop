<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-page, per-day Google performance. post_id is resolved from the URL path
 * during sync (null for non-post pages: home, categories, deals, tools).
 */
class SearchPageDay extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'position' => 'decimal:2',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
