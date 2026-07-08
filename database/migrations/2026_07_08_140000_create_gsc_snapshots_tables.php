<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_page_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->text('page_url');
            $table->string('path');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('position', 8, 2)->nullable();
            $table->string('page_type')->default('other');
            $table->timestamps();

            $table->unique(['date', 'path']);
            $table->index(['date', 'page_type']);
        });

        Schema::create('gsc_query_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('query', 500);
            $table->text('page_url')->nullable();
            $table->string('path')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('position', 8, 2)->nullable();
            $table->string('page_type')->nullable();
            $table->timestamps();

            $table->unique(['date', 'query', 'path']);
            $table->index(['date', 'page_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_query_snapshots');
        Schema::dropIfExists('gsc_page_snapshots');
    }
};
