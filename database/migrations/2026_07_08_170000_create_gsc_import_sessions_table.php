<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gsc_import_sessions')) {
            Schema::create('gsc_import_sessions', function (Blueprint $table): void {
                $table->id();
                $table->date('import_date')->index();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status')->default('pending')->index();
                $table->unsignedInteger('total_files')->default(0);
                $table->unsignedInteger('processed_files')->default(0);
                $table->unsignedInteger('skipped_files')->default(0);
                $table->unsignedInteger('pages_imported')->default(0);
                $table->unsignedInteger('queries_imported')->default(0);
                $table->unsignedInteger('countries_imported')->default(0);
                $table->unsignedInteger('devices_imported')->default(0);
                $table->unsignedInteger('search_appearances_imported')->default(0);
                $table->unsignedInteger('date_rows_imported')->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->json('warnings')->nullable();
                $table->json('errors')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_import_sessions');
    }
};
