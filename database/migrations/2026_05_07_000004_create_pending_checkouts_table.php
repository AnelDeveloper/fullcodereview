<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of every LS checkout we initiate. We can't trust LS to give
 * us back our custom_data via the Orders API, so we persist what we'd
 * need to credit the user when we see a paid order land. Match by
 * (user_email, usd_total_cents, recency) at sync time.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pending_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ls_checkout_id')->unique();
            $table->json('category_keys');
            $table->integer('usd_total_cents');
            $table->integer('discount_pct')->default(0);
            $table->string('status', 20)->default('pending'); // pending | completed
            $table->string('matched_order_id')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index('matched_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_checkouts');
    }
};
