<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convert redeem_codes from "one row per purchase, holding an array of
 * categories" to "one row per category slot". A user who buys
 * Security + Database now ends up with two rows — one of each
 * category — and the review run can consume any subset.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('redeem_codes', function (Blueprint $table) {
            // Per-slot category. Nullable for now to allow safe migration of
            // any existing rows; webhook always sets it on new rows.
            $table->string('category', 50)->nullable()->after('amount_cents')->index();

            // Which analysis consumed this slot (replaces the analysis ↔ code
            // 1:1 link, since a single analysis now consumes multiple slots).
            $table->foreignId('used_by_analysis_id')
                ->nullable()
                ->after('used_at')
                ->constrained('analyses')
                ->nullOnDelete();
        });

        // Drop the old per-purchase array — now stored implicitly via the
        // category column on each slot.
        if (Schema::hasColumn('redeem_codes', 'selected_categories')) {
            Schema::table('redeem_codes', function (Blueprint $table) {
                $table->dropColumn('selected_categories');
            });
        }

        // Code + email are no longer surfaced to users (we don't email codes
        // anymore, the slot just appears in the navbar). Make them nullable.
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
            $table->string('email')->nullable()->change();
        });

        // analyses.redeem_code_id was a 1:1 FK back to a single redemption.
        // Drop it — provenance is now reverse-tracked via
        // redeem_codes.used_by_analysis_id.
        if (Schema::hasColumn('analyses', 'redeem_code_id')) {
            Schema::table('analyses', function (Blueprint $table) {
                $table->dropForeign(['redeem_code_id']);
                $table->dropColumn('redeem_code_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->dropForeign(['used_by_analysis_id']);
            $table->dropColumn(['category', 'used_by_analysis_id']);
            $table->json('selected_categories')->nullable();
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->foreignId('redeem_code_id')->nullable()->constrained('redeem_codes')->nullOnDelete();
        });
    }
};
