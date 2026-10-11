<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Optional 16:9 recap video rendered by the drop-studio sibling repo
            // (plan 10.7). 16:9 is the render contract, so no width/height
            // columns; duration in whole seconds feeds VideoObject JSON-LD.
            $table->string('recap_video_path')->nullable()->after('hero_image_position');
            $table->unsignedInteger('recap_video_duration')->nullable()->after('recap_video_path');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['recap_video_path', 'recap_video_duration']);
        });
    }
};
