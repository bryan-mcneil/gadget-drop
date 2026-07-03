<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            // Per-post noindex: keep a thin/legacy post live for readers while
            // taking it out of Google's index (and the sitemap).
            $table->boolean('noindex')->default(false)->after('canonical_url');
        });
    }

    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropColumn('noindex');
        });
    }
};
