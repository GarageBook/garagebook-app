<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->date('ridden_at')->nullable()->after('description');
            $table->json('photos')->nullable()->after('source_format');

            $table->index(['vehicle_id', 'ridden_at']);
        });

        DB::table('trip_logs')
            ->whereNull('ridden_at')
            ->whereNotNull('started_at')
            ->update([
                'ridden_at' => DB::raw('DATE(started_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('trip_logs', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'ridden_at']);
            $table->dropColumn(['ridden_at', 'photos']);
        });
    }
};
