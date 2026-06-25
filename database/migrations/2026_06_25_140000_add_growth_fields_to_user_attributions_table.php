<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_attributions', function (Blueprint $table): void {
            $table->string('campaign_slug')->nullable()->after('source');
            $table->string('partner_slug')->nullable()->after('campaign_slug');
        });
    }

    public function down(): void
    {
        Schema::table('user_attributions', function (Blueprint $table): void {
            $table->dropColumn([
                'campaign_slug',
                'partner_slug',
            ]);
        });
    }
};
