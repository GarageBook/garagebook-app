<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table): void {
            $table->timestamp('demo_intro_shown_at')->nullable()->after('last_login_at');
            $table->timestamp('demo_intro_dismissed_at')->nullable()->after('demo_intro_shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table): void {
            $table->dropColumn(['demo_intro_shown_at', 'demo_intro_dismissed_at']);
        });
    }
};
