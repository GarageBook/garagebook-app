<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_logs', 'share_attachments_publicly')) {
                $table->boolean('share_attachments_publicly')->default(false)->after('file_attachments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_logs', 'share_attachments_publicly')) {
                $table->dropColumn('share_attachments_publicly');
            }
        });
    }
};
