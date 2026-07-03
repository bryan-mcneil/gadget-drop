<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // When the price was last verified against the retailer — set on
            // every price save and by the admin "confirm unchanged" action.
            $table->timestamp('price_checked_at')->nullable()->index()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['price_checked_at']);
            $table->dropColumn('price_checked_at');
        });
    }
};
