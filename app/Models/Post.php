<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'slug', 'excerpt', 'body',
        'source_url', 'featured_image', 'featured_image_fit',
        'image_1', 'image_1_fit', 'image_2', 'image_2_fit', 'image_3', 'image_3_fit',
        'status', 'published_at', 'view_count',
        'rating', 'pros', 'cons',
    ];

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
