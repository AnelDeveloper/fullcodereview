<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Drops the legacy `redeem_codes_lemon_order_id_unique` constraint that
 * was carried over when the table was renamed from `redeem_codes` to
 * `section_slots`. The original "one redemption code per order" model
 * is gone — we now issue one slot PER CATEGORY per order, so an order
 * with 4 categories produces 4 slots that all share the same
 * lemon_order_id.
 *
 * Replaces it with a composite unique on (lemon_order_id, category),
 * which is the right grain: never credit the same category twice for
 * the same order, but allow N categories per order.
 */
return new class extends Migration {
    public function up(): void
    {
        // Drop the legacy unique on lemon_order_id alone. Use raw SQL with
        // IF EXISTS because the constraint name kept its old `redeem_codes_`
        // prefix after the table rename.
        DB::statement('ALTER TABLE section_slots DROP CONSTRAINT IF EXISTS redeem_codes_lemon_order_id_unique');
        // Belt and braces in case some env got renamed:
        DB::statement('ALTER TABLE section_slots DROP CONSTRAINT IF EXISTS section_slots_lemon_order_id_unique');

        Schema::table('section_slots', function (Blueprint $table) {
            $table->unique(['lemon_order_id', 'category'], 'section_slots_order_category_unique');
        });
    }

    public function down(): void
    {
        Schema::table('section_slots', function (Blueprint $table) {
            $table->dropUnique('section_slots_order_category_unique');
            $table->unique('lemon_order_id', 'redeem_codes_lemon_order_id_unique');
        });
    }
};
