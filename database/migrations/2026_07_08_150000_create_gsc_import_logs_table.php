<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gsc_import_logs')) {
            Schema::create('gsc_import_logs', function (Blueprint $table): void {
                $table->id();
                $table->date('date')->index();
                $table->unsignedInteger('pages_imported')->default(0);
                $table->unsignedInteger('queries_imported')->default(0);
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->string('status')->default('pending')->index();
                $table->json('warnings')->nullable();
                $table->json('errors')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_import_logs');
    }
};
