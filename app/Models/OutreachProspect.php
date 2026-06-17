<?php

namespace App\Models;

use Database\Factories\OutreachProspectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachProspect extends Model
{
    private const DEMO_APP_URL = 'https://app.garagebook.nl';

    /** @use HasFactory<OutreachProspectFactory> */
    use HasFactory;

    protected $fillable = [
        'outreach_campaign_id',
        'company_name',
        'contact_name',
        'email',
        'website',
        'city',
        'token',
        'user_id',
        'clicked_at',
        'first_login_at',
        'last_login_at',
        'login_count',
        'notes',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'first_login_at' => 'datetime',
        'last_login_at' => 'datetime',
        'login_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (OutreachProspect $prospect): void {
            if (blank($prospect->token)) {
                $prospect->token = self::generateUniqueToken();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'outreach_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OutreachEvent::class);
    }

    public function demoUrl(): string
    {
        return self::DEMO_APP_URL . route('outreach.demo.login', ['token' => $this->token], false);
    }

    public static function generateUniqueToken(): string
    {
        do {
            $token = bin2hex(random_bytes(20));
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }
}
