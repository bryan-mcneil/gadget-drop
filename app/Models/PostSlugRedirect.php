<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A slug a post used to live at. /posts/{old_slug} 301s to the post's current
 * slug via the posts.show missing() fallback in routes/web.php. Rows are
 * recorded by PostObserver whenever a published post's slug changes, so manual
 * SEO slug edits in the admin never orphan an indexed URL.
 */
class PostSlugRedirect extends Model
{
    protected $fillable = ['post_id', 'old_slug'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The current slug to 301 to for an old slug, or null when the old slug is
     * unknown or its post is not published (matches PublicController::show's
     * status gate). Reads the live posts row, so chained renames (a → b → c)
     * resolve to the final slug in a single hop.
     */
    public static function resolve(string $oldSlug): ?string
    {
        return static::query()
            ->join('posts', 'posts.id', '=', 'post_slug_redirects.post_id')
            ->where('post_slug_redirects.old_slug', $oldSlug)
            ->where('posts.status', 'published')
            ->value('posts.slug');
    }
}
