<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->string('gtin', 14)->nullable()->after('asin');
            $table->decimal('amazon_rating', 2, 1)->nullable()->after('price');
            $table->unsignedMediumInteger('amazon_review_count')->nullable()->after('amazon_rating');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'gtin', 'amazon_rating', 'amazon_review_count']);
        });
    }
};
