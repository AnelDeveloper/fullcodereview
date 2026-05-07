<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `redeem_codes` was a misnomer once we moved to per-category slots.
 * Rename the table and drop the legacy columns that aren't used anymore.
 *
 * Final shape (matches what the app actually reads/writes):
 *   id, user_id, category, lemon_order_id, amount_cents,
 *   used_at, used_by_analysis_id, expires_at, created_at, updated_at
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::rename('redeem_codes', 'section_slots');

        Schema::table('section_slots', function (Blueprint $table) {
            // Legacy from the old "user types in a code" flow
            if (Schema::hasColumn('section_slots', 'code')) {
                $table->dropColumn('code');
            }
            // Redundant with users.email
            if (Schema::hasColumn('section_slots', 'email')) {
                $table->dropColumn('email');
            }
            // GitHub OAuth was previously stored per-code; now it's on users.
            if (Schema::hasColumn('section_slots', 'github_access_token')) {
                $table->dropColumn('github_access_token');
            }
            if (Schema::hasColumn('section_slots', 'github_login')) {
                $table->dropColumn('github_login');
            }
            if (Schema::hasColumn('section_slots', 'github_avatar_url')) {
                $table->dropColumn('github_avatar_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('section_slots', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('email')->nullable();
            $table->text('github_access_token')->nullable();
            $table->string('github_login')->nullable();
            $table->string('github_avatar_url')->nullable();
        });

        Schema::rename('section_slots', 'redeem_codes');
    }
};
