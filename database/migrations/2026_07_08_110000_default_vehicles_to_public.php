<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vehicles', 'is_public')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->boolean('is_public')->default(true)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vehicles', 'is_public')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->boolean('is_public')->default(false)->change();
        });
    }
};
