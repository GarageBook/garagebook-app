<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_email_logs', function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_email_logs', function (Blueprint $table): void {
            $table->dropColumn('queued_at');
        });
    }
};
