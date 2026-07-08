<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_authority_index', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->string('generation')->nullable();
            $table->string('category')->nullable();
            $table->string('slug')->unique();
            $table->unsignedInteger('vehicle_count')->default(0);
            $table->unsignedInteger('public_vehicle_count')->default(0);
            $table->boolean('is_indexable')->default(false);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            // Search Console columns – reserved for future GSC integration
            $table->unsignedBigInteger('organic_clicks')->nullable();
            $table->unsignedBigInteger('organic_impressions')->nullable();
            $table->decimal('ctr', 5, 4)->nullable();
            $table->decimal('average_position', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['brand', 'is_indexable']);
            $table->index(['public_vehicle_count', 'is_indexable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_authority_index');
    }
};
