<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outreach_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('city')->nullable();
            $table->string('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('first_login_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('clicked_at');
            $table->index('first_login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_prospects');
    }
};
