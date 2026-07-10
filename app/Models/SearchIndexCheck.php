<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result of one GSC URL Inspection call for a post.
 */
class SearchIndexCheck extends Model
{
    protected $guarded = [];

    protected $casts = [
        'raw' => 'array',
        'last_crawl_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** GSC's "PASS" verdict is the one that means indexed. */
    public function isIndexed(): bool
    {
        return $this->verdict === 'PASS';
    }
}
