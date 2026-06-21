<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('website');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');
            $table->string('country')->nullable()->after('postal_code');
            $table->string('source')->nullable()->after('country');
            $table->text('import_note')->nullable()->after('source');
            $table->timestamp('archived_at')->nullable()->after('import_note');

            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_prospects', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropColumn([
                'phone',
                'province',
                'postal_code',
                'country',
                'source',
                'import_note',
                'archived_at',
            ]);
        });
    }
};
