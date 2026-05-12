<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Switch payment provider from Lemon Squeezy → Stripe.
 *
 *   section_slots.lemon_order_id    → stripe_session_id
 *   pending_checkouts.ls_checkout_id → stripe_session_id
 *
 * The composite unique on section_slots also has to follow the rename so
 * Postgres doesn't keep complaining about the old constraint name.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('section_slots', function (Blueprint $table) {
            $table->dropUnique('section_slots_order_category_unique');
            $table->renameColumn('lemon_order_id', 'stripe_session_id');
        });

        Schema::table('section_slots', function (Blueprint $table) {
            $table->unique(['stripe_session_id', 'category'], 'section_slots_session_category_unique');
        });

        Schema::table('pending_checkouts', function (Blueprint $table) {
            $table->renameColumn('ls_checkout_id', 'stripe_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('section_slots', function (Blueprint $table) {
            $table->dropUnique('section_slots_session_category_unique');
            $table->renameColumn('stripe_session_id', 'lemon_order_id');
        });

        Schema::table('section_slots', function (Blueprint $table) {
            $table->unique(['lemon_order_id', 'category'], 'section_slots_order_category_unique');
        });

        Schema::table('pending_checkouts', function (Blueprint $table) {
            $table->renameColumn('stripe_session_id', 'ls_checkout_id');
        });
    }
};
