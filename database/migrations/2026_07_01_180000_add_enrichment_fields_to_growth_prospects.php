<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            if (! Schema::hasColumn('growth_prospects', 'suggested_email')) {
                $table->string('suggested_email')->nullable()->after('email');
            }

            if (! Schema::hasColumn('growth_prospects', 'suggested_email_confidence')) {
                $table->unsignedTinyInteger('suggested_email_confidence')->nullable()->after('suggested_email');
            }

            if (! Schema::hasColumn('growth_prospects', 'suggested_email_source_url')) {
                $table->string('suggested_email_source_url')->nullable()->after('suggested_email_confidence');
            }

            if (! Schema::hasColumn('growth_prospects', 'enrichment_notes')) {
                $table->text('enrichment_notes')->nullable()->after('quality_reason');
            }
        });

        if (! Schema::hasColumn('growth_prospects', 'suggested_email_confidence')) {
            Schema::table('growth_prospects', function (Blueprint $table): void {
                $table->index('suggested_email_confidence');
            });
        }
    }

    public function down(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('growth_prospects', 'suggested_email') ? 'suggested_email' : null,
                Schema::hasColumn('growth_prospects', 'suggested_email_confidence') ? 'suggested_email_confidence' : null,
                Schema::hasColumn('growth_prospects', 'suggested_email_source_url') ? 'suggested_email_source_url' : null,
                Schema::hasColumn('growth_prospects', 'enrichment_notes') ? 'enrichment_notes' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
