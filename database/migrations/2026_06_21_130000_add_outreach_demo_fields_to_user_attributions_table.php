<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_attributions', function (Blueprint $table): void {
            $table->string('source')->nullable()->after('user_id');
            $table->unsignedBigInteger('demo_user_id')->nullable()->after('source');
            $table->unsignedBigInteger('outreach_prospect_id')->nullable()->after('demo_user_id');
            $table->string('intended')->nullable()->after('outreach_prospect_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_attributions', function (Blueprint $table): void {
            $table->dropColumn([
                'source',
                'demo_user_id',
                'outreach_prospect_id',
                'intended',
            ]);
        });
    }
};
