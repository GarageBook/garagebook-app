<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->nullable()->after('year');
            $table->decimal('insurance_cost_per_month', 10, 2)->nullable()->after('purchase_price');
            $table->decimal('road_tax_cost_per_month', 10, 2)->nullable()->after('insurance_cost_per_month');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price',
                'insurance_cost_per_month',
                'road_tax_cost_per_month',
            ]);
        });
    }
};
