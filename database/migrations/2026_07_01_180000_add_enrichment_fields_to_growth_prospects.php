<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('growth_prospects', 'suggested_email')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->string('suggested_email')->nullable();
            });
        }

        if (! Schema::hasColumn('growth_prospects', 'suggested_email_confidence')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->unsignedTinyInteger('suggested_email_confidence')->nullable();
            });
        }

        if (! Schema::hasColumn('growth_prospects', 'suggested_email_source_url')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->string('suggested_email_source_url')->nullable();
            });
        }

        if (! Schema::hasColumn('growth_prospects', 'enrichment_notes')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->text('enrichment_notes')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('growth_prospects', 'suggested_email')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->dropColumn('suggested_email');
            });
        }

        if (Schema::hasColumn('growth_prospects', 'suggested_email_confidence')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->dropColumn('suggested_email_confidence');
            });
        }

        if (Schema::hasColumn('growth_prospects', 'suggested_email_source_url')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->dropColumn('suggested_email_source_url');
            });
        }

        if (Schema::hasColumn('growth_prospects', 'enrichment_notes')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->dropColumn('enrichment_notes');
            });
        }
    }
};
