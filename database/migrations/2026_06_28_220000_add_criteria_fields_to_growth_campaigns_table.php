<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table): void {
            $table->text('stop_criteria')->nullable()->after('ends_at');
            $table->text('scale_criteria')->nullable()->after('stop_criteria');
            $table->text('kpi_notes')->nullable()->after('scale_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('growth_campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'stop_criteria',
                'scale_criteria',
                'kpi_notes',
            ]);
        });
    }
};
