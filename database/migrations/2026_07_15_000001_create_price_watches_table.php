<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->decimal('purchase_price', 10, 2);
            $table->date('purchased_at');
            // Computed at insert as purchased_at + watch.window_days (see PriceWatch::booted()).
            $table->date('expires_at');
            // Magic-link identifier; paired with a signed URL on verify/unsubscribe.
            $table->uuid('token')->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            // Stamped by the Phase 3.4 closing-window mail; added now to avoid a later migration.
            $table->timestamp('closing_mail_sent_at')->nullable();
            // Abuse forensics only; never rendered, pruned with the row.
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index(['product_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_watches');
    }
};
