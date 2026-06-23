<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('lifecycle_email_logs', 'trigger')) {
                $table->string('trigger')->nullable()->after('email_key');
                $table->index('trigger', 'lifecycle_email_logs_trigger_index');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'mail_class')) {
                $table->string('mail_class')->nullable()->after('subject');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('status');
                $table->index('queued_at', 'lifecycle_email_logs_queued_at_index');
            }

            if (! Schema::hasColumn('lifecycle_email_logs', 'error')) {
                $table->text('error')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('lifecycle_email_logs', 'error')) {
                $table->dropColumn('error');
            }

            if (Schema::hasColumn('lifecycle_email_logs', 'queued_at')) {
                $table->dropIndex('lifecycle_email_logs_queued_at_index');
                $table->dropColumn('queued_at');
            }

            if (Schema::hasColumn('lifecycle_email_logs', 'mail_class')) {
                $table->dropColumn('mail_class');
            }

            if (Schema::hasColumn('lifecycle_email_logs', 'trigger')) {
                $table->dropIndex('lifecycle_email_logs_trigger_index');
                $table->dropColumn('trigger');
            }
        });
    }
};
