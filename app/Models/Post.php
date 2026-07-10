<?php

namespace App\Models;

use App\Observers\PostObserver;
use App\Support\NavigationData;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[ObservedBy(PostObserver::class)]
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

        // Bust the cached navigation sections when a post changes, but ignore the
        // per-view view_count bump (show() increments it on every page load) so a
        // simple page view doesn't nuke the cache.
        static::saved(function (Post $post) {
            $changed = array_keys($post->getChanges());
            if ($changed !== [] && array_diff($changed, ['view_count', 'updated_at']) === []) {
                return;
            }
            NavigationData::flush();
        });

        static::deleted(fn () => NavigationData::flush());
    }

    protected $casts = [
        'published_at' => 'datetime',
        'rating' => 'decimal:1',
        'pros' => 'array',
        'cons' => 'array',
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
