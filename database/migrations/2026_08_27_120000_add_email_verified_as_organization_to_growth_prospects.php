<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            $table->boolean('email_verified_as_organization')
                ->default(false)
                ->after('verification_required');
        });
    }

    public function down(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            $table->dropColumn('email_verified_as_organization');
        });
    }
};
