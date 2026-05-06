<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('redeem_code_id')->nullable()->constrained('redeem_codes')->nullOnDelete();
            $table->string('repo_full_name');
            $table->string('repo_url')->nullable();
            $table->string('repo_default_branch')->nullable();
            $table->unsignedInteger('files_scanned')->default(0);
            $table->unsignedBigInteger('lines_analyzed')->default(0);
            $table->unsignedTinyInteger('overall_score')->default(0);
            $table->unsignedTinyInteger('security_score')->default(0);
            $table->unsignedTinyInteger('performance_score')->default(0);
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->json('issues_json')->nullable();
            $table->text('summary')->nullable();
            $table->string('status')->default('completed');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
