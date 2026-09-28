<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date');
            $table->string('source_type');
            $table->string('definition_version');
            $table->longText('raw_text');
            $table->char('raw_text_hash', 64)->unique();
            $table->string('parser_version');
            $table->timestamp('imported_at');
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('parse_status');
            $table->json('warnings')->nullable();
            $table->timestamps();
        });

        Schema::create('weekly_report_snapshot_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('weekly_report_snapshots')->cascadeOnDelete();
            $table->string('metric_key');
            $table->decimal('value_numeric', 16, 4)->nullable();
            $table->unsignedInteger('numerator')->nullable();
            $table->unsignedInteger('denominator')->nullable();
            $table->string('unit');
            $table->string('definition_version');
            $table->string('raw_label');
            $table->timestamps();

            $table->unique(['snapshot_id', 'metric_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_report_snapshot_metrics');
        Schema::dropIfExists('weekly_report_snapshots');
    }
};
