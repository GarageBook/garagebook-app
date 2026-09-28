<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('record_origin')->nullable()->after('registration_source');
            $table->timestamp('source_created_at')->nullable()->after('record_origin');
            $table->string('onboarding_version')->nullable()->after('source_created_at');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('record_origin')->nullable()->after('airtable_synced_at');
            $table->timestamp('source_created_at')->nullable()->after('record_origin');
            $table->timestamp('first_published_at')->nullable()->after('source_created_at');
        });

        Schema::table('maintenance_logs', function (Blueprint $table): void {
            $table->string('record_origin')->nullable()->after('airtable_synced_at');
            $table->timestamp('source_created_at')->nullable()->after('record_origin');
            $table->timestamp('reminder_first_enabled_at')->nullable()->after('source_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table): void {
            $table->dropColumn(['record_origin', 'source_created_at', 'reminder_first_enabled_at']);
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn(['record_origin', 'source_created_at', 'first_published_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['record_origin', 'source_created_at', 'onboarding_version']);
        });
    }
};
