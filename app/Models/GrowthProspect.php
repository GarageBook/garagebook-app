<?php

namespace App\Models;

use Database\Factories\GrowthProspectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthProspect extends Model
{
    /** @use HasFactory<GrowthProspectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'website',
        'category',
        'subcategory',
        'region',
        'estimated_reach',
        'newsletter_status',
        'primary_contact_channel',
        'contact_name',
        'email',
        'priority',
        'warmth',
        'score',
        'status',
        'campaign_id',
        'partner_slug',
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
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }
}
