<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drop_price_puzzles', function (Blueprint $table) {
            $table->id();
            // Sequential, human-facing number — "Drop Price #142". Nullable because a
            // future-dated admin preset is queued before it has a number; the number is
            // assigned (max+1) only when the puzzle is actually locked for its date, so
            // numbering stays chronological even when presets are queued ahead of time.
            $table->unsignedInteger('puzzle_number')->nullable()->unique();
            // One puzzle per UTC date.
            $table->date('date')->unique();
            // The product the puzzle is based on. nullOnDelete so deleting a
            // product never destroys puzzle history (snapshots below survive).
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // The snapshotted whole-dollar answer — the secret. Frozen at lock time.
            $table->unsignedInteger('price');
            // Display snapshots so old puzzles still render after product edits/deletes.
            $table->string('product_name');
            $table->string('product_image_url', 512)->nullable();
            // Product the reveal CTA links to (usually == product_id; kept separate
            // so a nulled product_id doesn't break the affiliate route).
            $table->unsignedBigInteger('affiliate_product_id')->nullable();
            $table->timestamp('locked_at')->nullable();
            // Admin pre-set/override flag (a row queued ahead of time for a future date).
            $table->boolean('is_preset')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drop_price_puzzles');
    }
};
