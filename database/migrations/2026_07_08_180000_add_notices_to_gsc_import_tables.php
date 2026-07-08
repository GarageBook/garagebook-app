<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gsc_import_sessions') && ! Schema::hasColumn('gsc_import_sessions', 'notices')) {
            Schema::table('gsc_import_sessions', function (Blueprint $table): void {
                $table->json('notices')->nullable()->after('warnings');
            });
        }

        if (Schema::hasTable('gsc_import_logs') && ! Schema::hasColumn('gsc_import_logs', 'notices')) {
            Schema::table('gsc_import_logs', function (Blueprint $table): void {
                $table->json('notices')->nullable()->after('warnings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gsc_import_sessions') && Schema::hasColumn('gsc_import_sessions', 'notices')) {
            Schema::table('gsc_import_sessions', function (Blueprint $table): void {
                $table->dropColumn('notices');
            });
        }

        if (Schema::hasTable('gsc_import_logs') && Schema::hasColumn('gsc_import_logs', 'notices')) {
            Schema::table('gsc_import_logs', function (Blueprint $table): void {
                $table->dropColumn('notices');
            });
        }
    }
};
