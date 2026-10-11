<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('image_1_caption')->nullable()->after('image_1_fit');
            $table->string('image_2_caption')->nullable()->after('image_2_fit');
            $table->string('image_3_caption')->nullable()->after('image_3_fit');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['image_1_caption', 'image_2_caption', 'image_3_caption']);
        });
    }
};
