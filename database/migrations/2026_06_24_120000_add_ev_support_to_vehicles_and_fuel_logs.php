<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('powertrain_type')
                ->default('petrol')
                ->after('distance_unit');
            $table->decimal('home_kwh_rate', 8, 3)
                ->nullable()
                ->after('road_tax_cost_per_month');
        });

        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->string('entry_type')
                ->default('fuel')
                ->after('vehicle_id');
            $table->decimal('distance_km', 10, 1)
                ->nullable()
                ->change();
            $table->decimal('fuel_liters', 8, 2)
                ->nullable()
                ->change();
            $table->decimal('energy_kwh', 8, 2)
                ->nullable()
                ->after('fuel_liters');
            $table->decimal('price_per_kwh', 8, 3)
                ->nullable()
                ->after('price_per_liter');
            $table->decimal('total_cost', 10, 2)
                ->nullable()
                ->after('price_per_kwh');
            $table->string('charge_type')
                ->nullable()
                ->after('total_cost');
            $table->text('notes')
                ->nullable()
                ->after('station_location');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn([
                'entry_type',
                'energy_kwh',
                'price_per_kwh',
                'total_cost',
                'charge_type',
                'notes',
            ]);
            $table->decimal('distance_km', 10, 1)
                ->nullable(false)
                ->change();
            $table->decimal('fuel_liters', 8, 2)
                ->nullable(false)
                ->change();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'powertrain_type',
                'home_kwh_rate',
            ]);
        });
    }
};
