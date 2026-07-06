<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    public const STATUS_PENDING = 'pending'; // queued, an API driver will pick it up
    public const STATUS_READY = 'ready';     // composed, waiting for a manual copy-paste
    public const STATUS_POSTED = 'posted';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'post_id', 'platform', 'status', 'body',
        'external_url', 'last_error', 'attempts', 'posted_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'attempts'  => 'integer',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_READY);
    }
}
