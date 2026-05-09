<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the production-readiness scoring layer + human-verification workflow
 * on top of the existing analyses table. All columns are nullable / defaulted
 * so existing rows continue to work unchanged.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            // Readiness layer (computed from existing scores + issues_json — no AI)
            $table->unsignedTinyInteger('readiness_score')->nullable()->after('quality_score');
            $table->string('readiness_status', 32)->nullable()->after('readiness_score');
            $table->unsignedSmallInteger('critical_blocker_count')->nullable()->after('readiness_status');
            $table->unsignedSmallInteger('high_blocker_count')->nullable()->after('critical_blocker_count');

            // Executive summary — structured (business risks, top critical, plain English, next steps)
            $table->json('executive_summary_json')->nullable()->after('issues_json');

            // Human verification workflow
            $table->string('verification_status', 32)->default('ai_scan_complete')->after('status');
            $table->foreignId('reviewer_id')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            $table->text('reviewer_notes')->nullable()->after('reviewer_id');
            $table->text('internal_comments')->nullable()->after('reviewer_notes');
            $table->timestamp('verified_at')->nullable()->after('internal_comments');

            // Index for the reviewer queue page
            $table->index('verification_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_reviewer')->default(false)->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn([
                'readiness_score',
                'readiness_status',
                'critical_blocker_count',
                'high_blocker_count',
                'executive_summary_json',
                'verification_status',
                'reviewer_id',
                'reviewer_notes',
                'internal_comments',
                'verified_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_reviewer');
        });
    }
};
