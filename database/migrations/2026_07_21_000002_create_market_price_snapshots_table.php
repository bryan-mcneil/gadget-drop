<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->index(['market_product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_snapshots');
    }
};
