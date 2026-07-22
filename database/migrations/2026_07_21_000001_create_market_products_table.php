<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_products', function (Blueprint $table) {
            $table->id();
            // The market layer's identity: one row per ASIN ever seen in an
            // import. Deliberately NOT linked to products (whose asin is not
            // unique) — matches are resolved by ASIN join at import time.
            $table->char('asin', 10)->unique();
            $table->string('title', 500); // Amazon titles exceed 255
            // Second segment of the scraped title block (or an explicit
            // description column) — see MarketImportService::splitTitleBlock().
            $table->string('description', 500)->nullable();
            $table->string('brand')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('url', 500)->nullable();
            $table->decimal('current_price', 10, 2);
            $table->decimal('list_price', 10, 2)->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            // dateTime, not timestamp: MySQL gives the first NOT NULL
            // timestamp column an implicit ON UPDATE CURRENT_TIMESTAMP when
            // explicit_defaults_for_timestamp is off, and first_seen_at must
            // never auto-update.
            $table->dateTime('first_seen_at');
            // Snapshots are change-only; last_seen_at carries "observed"
            // truth for same-price sightings and drives coverage stats.
            $table->dateTime('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_products');
    }
};
