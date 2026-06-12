<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->timestamp('retried_at')->nullable()->after('error_message');
            $table->string('retry_status')->nullable()->after('retried_at');
            $table->unsignedBigInteger('retry_log_id')->nullable()->after('retry_status');
            $table->text('retry_error_message')->nullable()->after('retry_log_id');

            $table->index('retried_at');
            $table->index('retry_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->dropIndex(['retried_at']);
            $table->dropIndex(['retry_log_id']);
            $table->dropColumn([
                'retried_at',
                'retry_status',
                'retry_log_id',
                'retry_error_message',
            ]);
        });
    }
};
