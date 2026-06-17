<?php

namespace App\Models;

use App\Services\Outreach\OutreachEmailService;
use Database\Factories\OutreachCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachCampaign extends Model
{
    /** @use HasFactory<OutreachCampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'email_subject',
        'email_body',
    ];

    public function prospects(): HasMany
    {
        return $this->hasMany(OutreachProspect::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(OutreachEmailLog::class);
    }

    public function defaultEmailSubject(): string
    {
        return app(OutreachEmailService::class)->defaultSubject();
    }

    public function defaultEmailBody(): string
    {
        return app(OutreachEmailService::class)->defaultBody();
    }
}
