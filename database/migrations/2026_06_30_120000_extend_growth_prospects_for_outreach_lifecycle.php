<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            $table->string('organization_key')->nullable()->after('website')->index();
            $table->string('normalized_domain')->nullable()->after('organization_key')->index();
            $table->string('normalized_email')->nullable()->after('email')->index();
            $table->string('phone')->nullable()->after('normalized_email')->index();
            $table->string('city')->nullable()->after('phone');
            $table->string('prospect_type')->nullable()->after('subcategory')->index();
            $table->string('prospect_subtype')->nullable()->after('prospect_type')->index();
            $table->string('email_status')->default('missing')->after('normalized_email')->index();
            $table->boolean('verification_required')->default(false)->after('email_status')->index();
            $table->string('lifecycle_status')->default('new')->after('status')->index();
            $table->foreignId('last_campaign_id')->nullable()->after('campaign_id')->constrained('growth_campaigns')->nullOnDelete();
            $table->string('last_campaign_slug')->nullable()->after('last_campaign_id')->index();
            $table->foreignId('duplicate_of_id')->nullable()->after('last_campaign_slug')->constrained('growth_prospects')->nullOnDelete();
            $table->string('skip_reason')->nullable()->after('duplicate_of_id')->index();
            $table->string('source_url')->nullable()->after('skip_reason');
            $table->string('source_type')->nullable()->after('source_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('growth_prospects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_campaign_id');
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropColumn([
                'organization_key',
                'normalized_domain',
                'normalized_email',
                'phone',
                'city',
                'prospect_type',
                'prospect_subtype',
                'email_status',
                'verification_required',
                'lifecycle_status',
                'last_campaign_slug',
                'skip_reason',
                'source_url',
                'source_type',
            ]);
        });
    }
};
