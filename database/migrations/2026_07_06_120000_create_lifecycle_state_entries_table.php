<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_state_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('state');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'exited_at']);
            $table->index(['state', 'entered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_state_entries');
    }
};
