<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_attributions', function (Blueprint $table): void {
            $table->string('prospect_id')->nullable()->after('partner_slug');
        });
    }

    public function down(): void
    {
        Schema::table('user_attributions', function (Blueprint $table): void {
            $table->dropColumn('prospect_id');
        });
    }
};
