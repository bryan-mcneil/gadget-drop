<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worth_it_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // 'worth' | 'skip' — a portable string, never a DB enum (sqlite tests
            // + the enum-widening lesson from posts.type).
            $table->string('choice', 8);
            // hash_hmac('sha256', session|ip, app.key) — 64 hex chars. No raw IP
            // is ever stored (GDPR-clean, matches the site's privacy posture).
            $table->char('voter_hash', 64);
            $table->timestamps();

            // One vote per identity per post — the double-vote gate.
            $table->unique(['post_id', 'voter_hash']);
            // Tally lookups (worth vs skip counts per post).
            $table->index(['post_id', 'choice']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worth_it_votes');
    }
};
