<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'first_login_at', 'last_login_at', 'first_booklet_downloaded_at', 'airtable_record_id', 'airtable_synced_at', 'consumption_unit', 'registration_source', 'lifecycle_emails_unsubscribed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    public const ADMIN_EMAIL = 'willemvanveelen@icloud.com';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return str($this->email)->lower()->value() === self::ADMIN_EMAIL;
    }

    public function isGeratelUser(): bool
    {
        return $this->registration_source === 'geratel';
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
            'last_login_at' => 'datetime',
            'lifecycle_emails_unsubscribed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
