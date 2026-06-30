<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_outreach_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('growth_prospect_id')->constrained('growth_prospects')->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('growth_campaigns')->nullOnDelete();
            $table->string('campaign_slug')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('reason')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(['growth_prospect_id', 'event_type']);
            $table->index(['campaign_slug', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_outreach_events');
    }
};
