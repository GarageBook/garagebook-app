<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gsc_country_snapshots')) {
            Schema::create('gsc_country_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->date('date');
                $table->string('country');
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->decimal('position', 8, 2)->nullable();
                $table->timestamps();

                $table->unique(['date', 'country']);
            });
        }

        if (! Schema::hasTable('gsc_device_snapshots')) {
            Schema::create('gsc_device_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->date('date');
                $table->string('device');
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->decimal('position', 8, 2)->nullable();
                $table->timestamps();

                $table->unique(['date', 'device']);
            });
        }

        if (! Schema::hasTable('gsc_search_appearance_snapshots')) {
            Schema::create('gsc_search_appearance_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->date('date');
                $table->string('appearance');
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->decimal('position', 8, 2)->nullable();
                $table->timestamps();

                $table->unique(['date', 'appearance'], 'gsc_search_appearance_unique');
            });
        }

        if (! Schema::hasTable('gsc_date_snapshots')) {
            Schema::create('gsc_date_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->date('date');
                $table->date('data_date');
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('impressions')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->decimal('position', 8, 2)->nullable();
                $table->timestamps();

                $table->unique(['date', 'data_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_date_snapshots');
        Schema::dropIfExists('gsc_search_appearance_snapshots');
        Schema::dropIfExists('gsc_device_snapshots');
        Schema::dropIfExists('gsc_country_snapshots');
    }
};
