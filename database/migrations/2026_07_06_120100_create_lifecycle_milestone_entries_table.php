<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_milestone_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('milestone');
            $table->timestamp('achieved_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'milestone']);
            $table->index(['milestone', 'achieved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_milestone_entries');
    }
};
