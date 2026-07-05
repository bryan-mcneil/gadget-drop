<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The query a post is written to win. Optional, set from the pipeline
 * (Target query: line) so every post's intent is measurable — Phase 6 grades
 * the actual top queries 28 days after publish against this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->string('target_query', 255)->nullable()->after('focus_keyword');
        });
    }

    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropColumn('target_query');
        });
    }
};
