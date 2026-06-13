<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_logs', 'hide_photos_on_public_page')) {
                $table->boolean('hide_photos_on_public_page')->default(false)->after('share_attachments_publicly');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_logs', 'hide_photos_on_public_page')) {
                $table->dropColumn('hide_photos_on_public_page');
            }
        });
    }
};
