<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search Intel storage — the local tables that GSC + Bing data flows into.
 *
 * Schema-builder types only (no MySQL-only DDL), so the same migration runs
 * clean on MySQL (prod/local) and the sqlite :memory: test database with no
 * driver guard. Long natural keys (query, page_url) are deduplicated by sha1
 * hash columns because utf8mb4 unique indexes cap at ~768 bytes — hashing the
 * key keeps the unique index inside that ceiling.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Site/day trend totals. One row per (engine, day, search type).
        Schema::create('search_site_days', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10);          // google | bing
            $table->date('date');
            $table->string('search_type', 20)->default('web'); // web | discover | googleNews
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['source', 'date', 'search_type']);
        });

        // Page/day performance. url_hash = sha1(page_url).
        Schema::create('search_page_days', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('page_url', 500);
            $table->char('url_hash', 40);
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['date', 'url_hash']);
            $table->index(['url_hash', 'date']);
            $table->index(['post_id', 'date']);
        });

        // Query x page / day (Google only). query_hash = sha1(query).
        Schema::create('search_query_days', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('query', 500);
            $table->char('query_hash', 40);
            $table->string('page_url', 500);
            $table->char('url_hash', 40);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['date', 'query_hash', 'url_hash']);
            $table->index(['query_hash', 'date']);
            $table->index(['url_hash', 'date']);
        });

        // Weekly snapshots of Bing's rolling ~6-month per-query aggregate. Bing's
        // API has no date range, so each capture is stamped with captured_on and
        // we model the drift over time rather than pretending it's per-day data.
        Schema::create('bing_query_stats', function (Blueprint $table) {
            $table->id();
            $table->string('query', 500);
            $table->char('query_hash', 40);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('avg_click_position', 6, 2)->nullable();
            $table->decimal('avg_impression_position', 6, 2)->nullable();
            $table->date('captured_on');
            $table->timestamps();

            $table->unique(['query_hash', 'captured_on']);
        });

        // Every announcement we make to a search engine (Phase 2 writes here).
        Schema::create('search_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500);
            $table->string('engine', 20);      // indexnow | google_sitemap
            $table->string('trigger', 20);     // publish | update | retry | manual
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['engine', 'submitted_at']);
        });

        // Per-URL index status from the GSC URL Inspection API (Phase 2 writes here).
        Schema::create('search_index_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('verdict', 40)->nullable();
            $table->string('coverage_state', 120)->nullable();
            $table->timestamp('last_crawl_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['post_id', 'checked_at']);
        });

        // Ranked, deduplicated opportunities from the miner (Phase 3 writes here).
        Schema::create('search_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);        // striking_distance | ctr_fix | content_gap | decay | cannibalization | rising | bing_gap
            // Stable per-topic identity the miner clusters on (post:ID | gap:head |
            // cann:hash | rising:head | decay:ID | bing:hash). Keyed on so a run
            // updates the existing row even when the representative phrasing flips.
            $table->string('cluster_key', 100)->nullable();
            $table->string('query', 500)->nullable();
            $table->char('query_hash', 40)->nullable();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('page_url', 500)->nullable();
            $table->decimal('score', 10, 2)->default(0);
            $table->json('evidence')->nullable();
            $table->string('status', 12)->default('open'); // open | planned | done | dismissed
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['kind', 'cluster_key']);
            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_opportunities');
        Schema::dropIfExists('search_index_checks');
        Schema::dropIfExists('search_submissions');
        Schema::dropIfExists('bing_query_stats');
        Schema::dropIfExists('search_query_days');
        Schema::dropIfExists('search_page_days');
        Schema::dropIfExists('search_site_days');
    }
};
