<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM column modification is MySQL-specific; sqlite (used in tests) stores
        // `type` as a plain string, so the constraint is unnecessary there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('article', 'tech_tip', 'tech_news') NOT NULL DEFAULT 'article'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE posts MODIFY COLUMN type ENUM('article', 'tech_tip') NOT NULL DEFAULT 'article'");
    }
};
