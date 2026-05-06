<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->json('selected_categories')->nullable()->after('amount_cents');
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->json('selected_categories')->nullable()->after('issues_json');
        });
    }

    public function down(): void
    {
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->dropColumn('selected_categories');
        });
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn('selected_categories');
        });
    }
};
