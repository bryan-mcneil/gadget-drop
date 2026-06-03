<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('article', 'tech_tip', 'tech_news') NOT NULL DEFAULT 'article'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('article', 'tech_tip') NOT NULL DEFAULT 'article'");
    }
};
