<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drop_price_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drop_price_puzzle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->boolean('won');
            $table->unsignedTinyInteger('guesses_used');
            // abs % off the best guess (×100), for the "closest miss" bragging stat.
            $table->unsignedSmallInteger('closest_miss_pct')->nullable();
            // = puzzle date, denormalized for fast streak/range queries.
            $table->date('played_on');
            $table->timestamps();

            // One result per subscriber per puzzle (upsert on save/sync).
            $table->unique(['subscriber_id', 'drop_price_puzzle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drop_price_results');
    }
};
