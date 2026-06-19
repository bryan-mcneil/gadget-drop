<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // Denormalized Drop Price snapshot — the server backup of the
            // localStorage streak, restored/synced when a player saves their email.
            $table->unsignedInteger('play_streak')->default(0);      // headline metric, survives losses
            $table->unsignedInteger('best_play_streak')->default(0);
            $table->unsignedInteger('win_streak')->default(0);       // resets on a loss
            $table->unsignedInteger('best_win_streak')->default(0);
            $table->unsignedInteger('total_plays')->default(0);
            $table->unsignedInteger('total_wins')->default(0);
            $table->unsignedSmallInteger('closest_miss_pct')->nullable(); // best ever
            $table->date('last_played_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn([
                'play_streak',
                'best_play_streak',
                'win_streak',
                'best_win_streak',
                'total_plays',
                'total_wins',
                'closest_miss_pct',
                'last_played_on',
            ]);
        });
    }
};
