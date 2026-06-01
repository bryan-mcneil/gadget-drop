<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('share_code', 8)->nullable()->unique()->after('view_count');
        });

        DB::table('posts')->whereNull('share_code')->orderBy('id')->each(function ($post) {
            do {
                $code = Str::random(8);
            } while (DB::table('posts')->where('share_code', $code)->exists());

            DB::table('posts')->where('id', $post->id)->update(['share_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('share_code');
        });
    }
};
