<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A log row for every announcement we make to a search engine (IndexNow ping
 * or Google sitemap resubmit).
 */
class SearchSubmission extends Model
{
    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'response_code' => 'integer',
    ];
}
