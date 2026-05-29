<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('featured_image_fit')->default('cover')->after('featured_image');
            $table->string('image_1_fit')->default('cover')->after('image_1');
            $table->string('image_2_fit')->default('cover')->after('image_2');
            $table->string('image_3_fit')->default('cover')->after('image_3');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['featured_image_fit', 'image_1_fit', 'image_2_fit', 'image_3_fit']);
        });
    }
};
