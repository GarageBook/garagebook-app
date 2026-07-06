<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_rule_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rule_name');
            $table->boolean('matched')->default(false);
            $table->text('reason')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamp('cooldown_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'rule_name', 'evaluated_at']);
            $table->index(['rule_name', 'matched', 'evaluated_at']);
            $table->index(['user_id', 'matched', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_rule_evaluations');
    }
};
