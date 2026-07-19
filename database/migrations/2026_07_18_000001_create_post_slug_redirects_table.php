<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slugs a post used to live at. When /posts/{slug} misses, the route's
     * missing() fallback (routes/web.php) looks the slug up here and 301s to
     * the post's CURRENT slug, so admin slug renames never orphan an indexed
     * URL. Rows are appended automatically by PostObserver on slug change.
     */
    public function up(): void
    {
        Schema::create('post_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug')->unique();
            $table->timestamps();
        });

        // Backfill the renames that predate this table (manual Screaming Frog
        // slug fixes, July 2026) plus the camera tip's mislinked eufy URL that
        // was live for a few days. Targets are looked up by current slug, so
        // this is a no-op on databases without the posts (fresh installs, the
        // sqlite test schema).
        $backfill = [
            'mega-raichu-x-lands-in-pokmon-champions-as-the-game-hits-mobile' => 'mega-raichu-x-lands-in-pokmon-champions',
            'mega-raichu-x-lands-in-pokémon-champions-as-the-game-hits-mobile' => 'mega-raichu-x-lands-in-pokmon-champions',
            'samsung-galaxy-unpacked-set-for-july-22-watch-9-fold-8' => 'samsung-galaxy-unpacked-set-for-july-22',
            'eufy-indoor-cam-e30-review' => 'eufy-indoor-cam-e30-review-4k-without-the-monthly-fee',
        ];

        $now = now();

        foreach ($backfill as $oldSlug => $currentSlug) {
            $postId = DB::table('posts')->where('slug', $currentSlug)->value('id');

            if ($postId) {
                DB::table('post_slug_redirects')->insert([
                    'post_id' => $postId,
                    'old_slug' => $oldSlug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_slug_redirects');
    }
};
