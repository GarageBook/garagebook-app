<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            $table->string('suggested_email')->nullable()->after('email');
            $table->unsignedTinyInteger('suggested_email_confidence')->nullable()->after('suggested_email')->index();
            $table->string('suggested_email_source_url')->nullable()->after('suggested_email_confidence');
            $table->text('enrichment_notes')->nullable()->after('quality_reason');
        });
    }

    public function down(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            $table->dropColumn([
                'suggested_email',
                'suggested_email_confidence',
                'suggested_email_source_url',
                'enrichment_notes',
            ]);
        });
    }
};
