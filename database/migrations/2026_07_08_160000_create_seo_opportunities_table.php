<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_opportunities')) {
            Schema::create('seo_opportunities', function (Blueprint $table): void {
                $table->id();
                $table->date('date')->index();
                $table->string('type')->index();
                $table->string('title');
                $table->text('description');
                $table->unsignedTinyInteger('impact_score')->index();
                $table->string('effort', 16);
                $table->string('priority', 16)->index();
                $table->text('page_url')->nullable();
                $table->string('path')->nullable()->index();
                $table->string('query', 500)->nullable();
                $table->string('page_type')->nullable()->index();
                $table->string('brand')->nullable()->index();
                $table->text('recommended_action');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['date', 'type', 'path', 'query'], 'seo_opportunities_unique_daily_item');
                $table->index(['date', 'impact_score']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_opportunities');
    }
};
