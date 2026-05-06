<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('redeem_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_session_id')->nullable()->unique();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('github_access_token')->nullable();
            $table->string('github_login')->nullable();
            $table->string('github_avatar_url')->nullable();
            $table->timestamps();

            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redeem_codes');
    }
};
