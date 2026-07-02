<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            if (! Schema::hasColumn('growth_prospects', 'quality_score')) {
                $table->unsignedTinyInteger('quality_score')->nullable()->after('source_type')->index();
            }

            if (! Schema::hasColumn('growth_prospects', 'quality_flags')) {
                $table->json('quality_flags')->nullable()->after('quality_score');
            }

            if (! Schema::hasColumn('growth_prospects', 'quality_verdict')) {
                $table->string('quality_verdict')->nullable()->after('quality_flags')->index();
            }

            if (! Schema::hasColumn('growth_prospects', 'quality_reason')) {
                $table->string('quality_reason')->nullable()->after('quality_verdict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            if (Schema::hasColumn('growth_prospects', 'quality_reason')) {
                $table->dropColumn('quality_reason');
            }

            if (Schema::hasColumn('growth_prospects', 'quality_verdict')) {
                $table->dropColumn('quality_verdict');
            }

            if (Schema::hasColumn('growth_prospects', 'quality_flags')) {
                $table->dropColumn('quality_flags');
            }

            if (Schema::hasColumn('growth_prospects', 'quality_score')) {
                $table->dropColumn('quality_score');
            }
        });
    }
};
