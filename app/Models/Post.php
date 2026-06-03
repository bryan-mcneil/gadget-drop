<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Post extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'slug', 'excerpt', 'body',
        'source_url', 'featured_image', 'featured_image_fit', 'featured_image_position',
        'hero_image', 'hero_image_position',
        'image_1', 'image_1_fit', 'image_2', 'image_2_fit', 'image_3', 'image_3_fit',
        'status', 'published_at', 'view_count',
        'rating', 'pros', 'cons',
        'share_code',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Post $post) {
            if (empty($post->share_code)) {
                do {
                    $code = Str::random(8);
                } while (static::where('share_code', $code)->exists());

                $post->share_code = $code;
            }
        });
    }

    protected $casts = [
        'published_at' => 'datetime',
        'rating'       => 'decimal:1',
        'pros'         => 'array',
        'cons'         => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'post_categories');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'post_products')
            ->withPivot('display_order')
            ->orderByPivot('display_order');
    }

    public function seoMeta(): HasOne
    {
        return $this->hasOne(SeoMeta::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }
}
