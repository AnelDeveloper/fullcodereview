<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Replace the plain unique(email) with a partial unique index so a
        // soft-deleted user's email doesn't block someone else from
        // registering / being created with that same email later. Postgres
        // partial indexes are exactly the right tool here.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->dropSoftDeletes();
        });
    }
};
