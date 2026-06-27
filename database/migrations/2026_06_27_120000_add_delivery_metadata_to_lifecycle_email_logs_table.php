<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('lifecycle_email_logs', 'mailer')) {
                $table->string('mailer')->nullable()->after('retry_error_message');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'mail_transport')) {
                $table->string('mail_transport')->nullable()->after('mailer');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'release_path')) {
                $table->string('release_path')->nullable()->after('mail_transport');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'queue_job_id')) {
                $table->string('queue_job_id')->nullable()->after('release_path');
                $table->index('queue_job_id', 'lifecycle_email_logs_queue_job_id_index');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'retry_of_log_id')) {
                $table->unsignedBigInteger('retry_of_log_id')->nullable()->after('queue_job_id');
                $table->index('retry_of_log_id', 'lifecycle_email_logs_retry_of_log_id_index');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'resend_message_id')) {
                $table->string('resend_message_id')->nullable()->after('retry_of_log_id');
                $table->index('resend_message_id', 'lifecycle_email_logs_resend_message_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('lifecycle_email_logs', 'resend_message_id')) {
                $table->dropIndex('lifecycle_email_logs_resend_message_id_index');
                $table->dropColumn('resend_message_id');
            }

            if (Schema::hasColumn('lifecycle_email_logs', 'retry_of_log_id')) {
                $table->dropIndex('lifecycle_email_logs_retry_of_log_id_index');
                $table->dropColumn('retry_of_log_id');
            }

            if (Schema::hasColumn('lifecycle_email_logs', 'queue_job_id')) {
                $table->dropIndex('lifecycle_email_logs_queue_job_id_index');
                $table->dropColumn('queue_job_id');
            }

            foreach (['release_path', 'mail_transport', 'mailer'] as $column) {
                if (Schema::hasColumn('lifecycle_email_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
