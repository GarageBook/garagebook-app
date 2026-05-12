<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('source_file_path');
            $table->string('source_file_name')->nullable();
            $table->string('source_format')->default('gpx');
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->decimal('distance_km', 10, 3)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('points_count')->nullable();
            $table->json('bounds')->nullable();
            $table->longText('geojson')->nullable();
            $table->longText('simplified_geojson')->nullable();
            $table->json('stats')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'vehicle_id', 'status']);
            $table->index(['vehicle_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_logs');
    }
};
