<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ranked, deduplicated action produced by the opportunity miner. `evidence`
 * carries the clustered query phrasings + the numbers behind `score`.
 */
class SearchOpportunity extends Model
{
    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'score' => 'decimal:2',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
