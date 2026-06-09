<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for the hot public-query paths.
     *
     * Every public listing filters by status='published' and published_at<=now
     * and sorts by published_at; trending sorts by view_count. None of those
     * columns were indexed. Pivot FK columns (category_id, tag_id, product_id)
     * and the affiliate_clicks FKs are already auto-indexed by their foreign-key
     * constraints, so only the missing non-FK indexes are added here.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // published-listing-by-date (home, news, category, search, related)
            $table->index(['status', 'published_at'], 'posts_status_published_at_index');
            // trending: filter by status, sort by view_count
            $table->index(['status', 'view_count'], 'posts_status_view_count_index');
        });

        Schema::table('affiliate_clicks', function (Blueprint $table) {
            // dashboard: whereDate('clicked_at', today())
            $table->index('clicked_at', 'affiliate_clicks_clicked_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_status_published_at_index');
            $table->dropIndex('posts_status_view_count_index');
        });

        Schema::table('affiliate_clicks', function (Blueprint $table) {
            $table->dropIndex('affiliate_clicks_clicked_at_index');
        });
    }
};
