<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereRaw('lower(email) = ?', ['willemvanveelen@icloud.com'])
            ->update(['is_admin' => true]);
    }

    public function down(): void
    {
        // Intentionally do not revoke admin rights on rollback; doing so could
        // lock the existing production owner account out of the admin panel.
    }
};
