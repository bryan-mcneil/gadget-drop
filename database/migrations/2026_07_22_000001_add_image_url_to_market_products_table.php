<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_products', function (Blueprint $table) {
            // Admin-curated only: the import contract has no image column and
            // MarketImportService never writes this field, so manual values
            // survive re-imports (unlike title/brand/category/description).
            $table->string('image_url', 500)->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('market_products', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
