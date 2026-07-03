<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * Minimum published posts before a category page is worth indexing.
     * Shared by the sitemap (inclusion) and PublicController::category()
     * (noindex) so the two signals never disagree.
     */
    public const SITEMAP_MIN_POSTS = 3;

    protected $fillable = ['name', 'slug', 'description', 'featured_image'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_categories');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
