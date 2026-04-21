<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->integer('interval_months')->nullable();
            $table->integer('interval_km')->nullable();

            $table->boolean('reminder_enabled')->default(false);

            $table->integer('last_km')->nullable();
            $table->date('last_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'interval_months',
                'interval_km',
                'reminder_enabled',
                'last_km',
                'last_date',
            ]);
        });
    }
};