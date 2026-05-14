<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_pages', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('page');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->nullable();
            $table->decimal('position', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['date', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_pages');
    }
};
