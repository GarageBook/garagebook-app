<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->text('reason_skipped')->nullable()->after('error_message');
            $table->unsignedInteger('vehicles_count')->nullable()->after('reason_skipped');
            $table->unsignedInteger('maintenance_logs_count')->nullable()->after('vehicles_count');
            $table->unsignedInteger('documents_count')->nullable()->after('maintenance_logs_count');
            $table->timestamp('last_login_at')->nullable()->after('documents_count');
            $table->timestamp('clicked_at')->nullable()->after('last_login_at');
            $table->timestamp('skipped_at')->nullable()->after('clicked_at');

            $table->index('clicked_at');
            $table->index('skipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->dropIndex(['clicked_at']);
            $table->dropIndex(['skipped_at']);
            $table->dropColumn([
                'reason_skipped',
                'vehicles_count',
                'maintenance_logs_count',
                'documents_count',
                'last_login_at',
                'clicked_at',
                'skipped_at',
            ]);
        });
    }
};
