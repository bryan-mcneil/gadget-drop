<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Social outbox: one row per (post, platform) records the intent to announce a
 * published post on that platform. The hourly social:publish command drains
 * pending rows through per-platform drivers; rows in "ready" wait for a manual
 * copy-paste via /admin/social. Statuses: pending | ready | posted | failed | skipped.
 * Plain string columns (not ENUM) so the sqlite test suite runs unmodified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('status', 20)->default('pending');
            $table->text('body');
            $table->string('external_url')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            // Idempotency: a post can only ever be announced once per platform.
            $table->unique(['post_id', 'platform']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
