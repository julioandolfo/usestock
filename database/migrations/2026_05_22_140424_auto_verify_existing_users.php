<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Auto-verify any user who registered before the email-verification
     * requirement was removed. This is idempotent: re-running does nothing
     * because we only target rows where email_verified_at is null.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // No-op: we can't determine which users were originally unverified.
    }
};
