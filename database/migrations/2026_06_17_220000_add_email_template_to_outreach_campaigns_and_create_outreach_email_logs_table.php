<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table): void {
            $table->string('email_subject')->nullable()->after('description');
            $table->longText('email_body')->nullable()->after('email_subject');
        });

        Schema::create('outreach_email_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outreach_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outreach_prospect_id')->constrained()->cascadeOnDelete();
            $table->string('to_email')->nullable();
            $table->string('subject');
            $table->longText('body_snapshot');
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['outreach_campaign_id', 'outreach_prospect_id', 'status'], 'outreach_email_logs_campaign_prospect_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_email_logs');

        Schema::table('outreach_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['email_subject', 'email_body']);
        });
    }
};
