<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_top_pages', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('page_path');
            $table->string('page_title')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('users')->nullable();
            $table->timestamps();

            $table->unique(['date', 'page_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_top_pages');
    }
};
