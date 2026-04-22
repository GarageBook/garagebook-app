<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('airtable_record_id')->nullable()->unique()->after('user_id');
            $table->timestamp('airtable_synced_at')->nullable()->after('photos');
        });

        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->string('airtable_record_id')->nullable()->unique()->after('vehicle_id');
            $table->timestamp('airtable_synced_at')->nullable()->after('attachments');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropUnique(['airtable_record_id']);
            $table->dropColumn(['airtable_record_id', 'airtable_synced_at']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['airtable_record_id']);
            $table->dropColumn(['airtable_record_id', 'airtable_synced_at']);
        });
    }
};
