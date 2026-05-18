<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Switching from a classic OAuth App (scope=repo, full read/write) to a
 * GitHub App with read-only Contents permission. The new flow stores an
 * installation_id rather than a long-lived user OAuth token — every API
 * call mints a fresh installation token from the App's private key.
 *
 * Existing tokens are wiped so users get prompted to reconnect through
 * the new install flow; the old OAuth grant on github.com side is left in
 * place (users can revoke it from github.com/settings/applications).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_installation_id')->nullable()->after('github_avatar_url');
        });

        // Force every connected user back through the new install flow.
        DB::table('users')
            ->whereNotNull('github_access_token')
            ->update([
                'github_access_token' => null,
                'github_login' => null,
                'github_avatar_url' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('github_installation_id');
        });
    }
};
