<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('github_access_token')->nullable()->after('api_token');
            $table->string('github_login')->nullable()->after('github_access_token');
            $table->string('github_avatar_url')->nullable()->after('github_login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['github_access_token', 'github_login', 'github_avatar_url']);
        });
    }
};
