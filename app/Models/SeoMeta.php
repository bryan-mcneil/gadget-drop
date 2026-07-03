<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'post_id', 'meta_title', 'meta_description',
        'focus_keyword', 'og_image', 'canonical_url', 'noindex',
    ];

    protected $casts = [
        'noindex' => 'boolean',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
