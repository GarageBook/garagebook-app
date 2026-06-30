<?php

namespace App\Models;

use Database\Factories\GrowthProspectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrowthProspect extends Model
{
    /** @use HasFactory<GrowthProspectFactory> */
    use HasFactory;

    public const EMAIL_STATUS_FOUND = 'found';

    public const EMAIL_STATUS_MISSING = 'missing';

    public const EMAIL_STATUS_VERIFIED = 'verified';

    public const EMAIL_STATUS_INVALID = 'invalid';

    public const EMAIL_STATUSES = [
        self::EMAIL_STATUS_FOUND,
        self::EMAIL_STATUS_MISSING,
        self::EMAIL_STATUS_VERIFIED,
        self::EMAIL_STATUS_INVALID,
    ];

    public const LIFECYCLE_NEW = 'new';

    public const LIFECYCLE_ENRICHED = 'enriched';

    public const LIFECYCLE_READY = 'ready';

    public const LIFECYCLE_MANUAL_REVIEW = 'manual_review';

    public const LIFECYCLE_CONTACTED = 'contacted';

    public const LIFECYCLE_REPLIED = 'replied';

    public const LIFECYCLE_INTERESTED = 'interested';

    public const LIFECYCLE_ARCHIVED = 'archived';

    public const LIFECYCLE_STATUSES = [
        self::LIFECYCLE_NEW,
        self::LIFECYCLE_ENRICHED,
        self::LIFECYCLE_READY,
        self::LIFECYCLE_MANUAL_REVIEW,
        self::LIFECYCLE_CONTACTED,
        self::LIFECYCLE_REPLIED,
        self::LIFECYCLE_INTERESTED,
        self::LIFECYCLE_ARCHIVED,
    ];

    public const PROSPECT_TYPES = ['community'];

    public const PROSPECT_SUBTYPES = [
        'oldtimer_club',
        'brand_club',
        'motorcycle_club',
        'car_club',
        'camper_club',
        'youngtimer_club',
        'trackday_community',
        'forum',
        'foundation',
        'association',
    ];

    protected $fillable = [
        'name',
        'website',
        'organization_key',
        'normalized_domain',
        'category',
        'subcategory',
        'prospect_type',
        'prospect_subtype',
        'region',
        'estimated_reach',
        'newsletter_status',
        'primary_contact_channel',
        'contact_name',
        'email',
        'normalized_email',
        'email_status',
        'verification_required',
        'phone',
        'city',
        'priority',
        'warmth',
        'score',
        'status',
        'lifecycle_status',
        'campaign_id',
        'last_campaign_id',
        'last_campaign_slug',
        'partner_slug',
        'duplicate_of_id',
        'skip_reason',
        'source_url',
        'source_type',
        'quality_score',
        'quality_flags',
        'quality_verdict',
        'quality_reason',
        'notes',
        'why_interesting',
        'approach_strategy',
        'last_contacted_at',
        'next_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'quality_score' => 'integer',
            'quality_flags' => 'array',
            'verification_required' => 'boolean',
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }

    public function lastCampaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'last_campaign_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function outreachEvents(): HasMany
    {
        return $this->hasMany(GrowthOutreachEvent::class);
    }
}
