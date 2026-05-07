<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('fuel_date');
            $table->decimal('odometer_km', 10, 1)->nullable();
            $table->decimal('distance_km', 10, 1);
            $table->decimal('fuel_liters', 8, 2);
            $table->decimal('price_per_liter', 8, 3)->nullable();
            $table->string('station_location')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'fuel_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
