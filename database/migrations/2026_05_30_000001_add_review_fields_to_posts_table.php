<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->decimal('rating', 3, 1)->nullable()->after('body');
            $table->json('pros')->nullable()->after('rating');
            $table->json('cons')->nullable()->after('pros');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['rating', 'pros', 'cons']);
        });
    }
};
