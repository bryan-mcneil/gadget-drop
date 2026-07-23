<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_cycles', function (Blueprint $table) {
            $table->id();
            // A product LINE, not a product: "iPhone", "AirPods Pro". Cycles are
            // editorial (curated + sourced); price curves stay product-level.
            $table->string('name');
            // Becomes the /buy-or-wait/{slug} URL; treat as permanent.
            $table->string('slug')->unique();
            // Optional link to a site category. Deliberately weak: several lines
            // share one hub (iPad + MacBook Air + Kindle are all "computers"),
            // so category is a fallback signal only; see ReleaseCycle::forProduct().
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // 1–12, the month the line typically refreshes. Nullable: some lines
            // (game consoles) have no meaningful seasonal slot.
            $table->unsignedTinyInteger('typical_month')->nullable();
            // Months between refreshes. 12 for phones, 36 for AirPods Pro, 96 for
            // the Switch generation. This is the denominator of the cycle position.
            $table->unsignedTinyInteger('cadence_months');
            $table->string('last_release_name');
            // The date the CURRENT model reached buyers (availability, not the
            // keynote) where the cited source states one; otherwise the
            // announcement date in that source. That is when the price curve
            // for the new model starts and the predecessor starts discounting.
            $table->date('last_release_at');
            // Editorial, hedged, history-only note ("Apple has announced a new
            // iPhone every September since 2012"). Never leaks or predictions.
            $table->string('next_expected_note')->nullable();
            // Mandatory honesty pair: where the facts came from, and when a human
            // last checked them against that source. Staleness is displayed, not
            // hidden; see ReleaseCycle::scopeStale().
            $table->string('source_url');
            $table->date('verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_cycles');
    }
};
