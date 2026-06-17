<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->timestamp('goal_completed_at')->nullable()->after('clicked_at');

            $table->index('goal_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('lifecycle_email_logs', function (Blueprint $table): void {
            $table->dropIndex(['goal_completed_at']);
            $table->dropColumn('goal_completed_at');
        });
    }
};
