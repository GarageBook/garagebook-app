<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_outreach_demo', 'first_login_at', 'last_login_at', 'first_booklet_downloaded_at', 'airtable_record_id', 'airtable_synced_at', 'consumption_unit', 'registration_source', 'record_origin', 'source_created_at', 'onboarding_version', 'lifecycle_emails_unsubscribed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    public const CURRENT_ONBOARDING_VERSION = '2026-09-activation-v1';

    public const ADMIN_EMAILS = [
        'willemvanveelen@icloud.com',
        'leroy@lenduria.nl',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            // Privileges cannot follow an email change or be assigned outside the allowlist.
            if (! self::isAllowedAdminEmail($user->email)
                || ($user->exists && $user->isDirty('email')
                    && strtolower(trim((string) $user->getOriginal('email'))) !== strtolower(trim((string) $user->email)))) {
                $user->is_admin = false;
            }
        });

        static::creating(function (User $user): void {
            $user->record_origin ??= filled($user->airtable_record_id) ? 'airtable_import' : 'native';
            if ($user->record_origin === 'native') {
                $user->source_created_at ??= now();
                $user->onboarding_version ??= self::CURRENT_ONBOARDING_VERSION;
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // GarageBook uses /admin for both the user app and admin-only management features,
        // so panel access must not be globally blocked by admin status.
        return true;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin && self::isAllowedAdminEmail($this->email);
    }

    public static function isAllowedAdminEmail(?string $email): bool
    {
        return in_array(strtolower(trim((string) $email)), self::ADMIN_EMAILS, true);
    }

    public function isGeratelUser(): bool
    {
        return $this->registration_source === 'geratel';
    }

    public function isCoreFunnelUser(): bool
    {
        if ($this->isAdmin() || (bool) $this->is_outreach_demo || in_array($this->registration_source, self::nonCoreFunnelSources(), true)) {
            return false;
        }

        $attribution = $this->relationLoaded('attribution') ? $this->attribution : $this->attribution()->first();

        if (! $attribution) {
            return true;
        }

        return ! in_array($attribution->source, self::nonCoreFunnelSources(), true)
            && $attribution->demo_user_id === null
            && $attribution->outreach_prospect_id === null;
    }

    public function scopeCoreFunnel(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('is_admin')
                ->orWhere('is_admin', false))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('is_outreach_demo')
                ->orWhere('is_outreach_demo', false))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('registration_source')
                ->orWhereNotIn('registration_source', self::nonCoreFunnelSources()))
            ->whereDoesntHave('attribution', function (Builder $query): void {
                $query
                    ->whereIn('source', self::nonCoreFunnelSources())
                    ->orWhereNotNull('demo_user_id')
                    ->orWhereNotNull('outreach_prospect_id');
            });
    }

    /**
     * @return array<int, string>
     */
    public static function nonCoreFunnelSources(): array
    {
        return [
            'outreach_demo',
            'demo',
            'test',
            'internal',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function tripLogs(): HasMany
    {
        return $this->hasMany(TripLog::class);
    }

    public function attribution(): HasOne
    {
        return $this->hasOne(UserAttribution::class);
    }

    public function lifecycleEmailLogs(): HasMany
    {
        return $this->hasMany(LifecycleEmailLog::class);
    }

    public function dailyActivities(): HasMany
    {
        return $this->hasMany(UserDailyActivity::class);
    }

    public function outreachProspect(): HasOne
    {
        return $this->hasOne(OutreachProspect::class);
    }

    public function hasUnsubscribedFromLifecycleEmails(): bool
    {
        return $this->lifecycle_emails_unsubscribed_at !== null;
    }

    protected function casts(): array
    {
        return [
            'airtable_synced_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'first_booklet_downloaded_at' => 'datetime',
            'first_login_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_outreach_demo' => 'boolean',
            'last_login_at' => 'datetime',
            'source_created_at' => 'datetime',
            'lifecycle_emails_unsubscribed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
