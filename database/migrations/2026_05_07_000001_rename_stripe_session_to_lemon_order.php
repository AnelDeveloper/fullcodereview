<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add the new column
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->string('lemon_order_id')->nullable()->unique()->after('user_id');
        });

        // Drop the old Stripe column if it exists
        if (Schema::hasColumn('redeem_codes', 'stripe_session_id')) {
            Schema::table('redeem_codes', function (Blueprint $table) {
                $table->dropUnique(['stripe_session_id']);
                $table->dropColumn('stripe_session_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->unique()->after('user_id');
            $table->dropUnique(['lemon_order_id']);
            $table->dropColumn('lemon_order_id');
        });
    }
};
