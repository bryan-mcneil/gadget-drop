<?php

namespace App\Models;

use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ProductObserver::class)]
class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'brand', 'asin', 'gtin', 'affiliate_url',
        'image_url', 'price', 'price_checked_at', 'amazon_rating', 'amazon_review_count', 'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_checked_at' => 'datetime',
        'amazon_rating' => 'decimal:1',
        'amazon_review_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_products')
            ->withPivot('display_order');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(ProductPriceSnapshot::class);
    }
}
