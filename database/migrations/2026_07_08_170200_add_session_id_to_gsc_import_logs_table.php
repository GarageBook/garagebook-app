<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gsc_import_logs') && ! Schema::hasColumn('gsc_import_logs', 'gsc_import_session_id')) {
            Schema::table('gsc_import_logs', function (Blueprint $table): void {
                $table->foreignId('gsc_import_session_id')->nullable()->after('id')->constrained('gsc_import_sessions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gsc_import_logs') && Schema::hasColumn('gsc_import_logs', 'gsc_import_session_id')) {
            Schema::table('gsc_import_logs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('gsc_import_session_id');
            });
        }
    }
};
