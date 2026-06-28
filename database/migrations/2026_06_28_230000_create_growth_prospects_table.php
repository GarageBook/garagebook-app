<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_prospects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('region')->nullable();
            $table->string('estimated_reach')->nullable();
            $table->string('newsletter_status')->nullable();
            $table->string('primary_contact_channel')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('priority')->nullable();
            $table->string('warmth')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->string('partner_slug')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->text('why_interesting')->nullable();
            $table->text('approach_strategy')->nullable();
            $table->dateTime('last_contacted_at')->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_prospects');
    }
};
