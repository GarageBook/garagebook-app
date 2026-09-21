<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->uuid('processing_token')->nullable()->after('queue_job_id');
            $table->index('processing_token');
        });
    }

    public function down(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->dropIndex(['processing_token']);
            $table->dropColumn('processing_token');
        });
    }
};
