<?php

namespace App\Services;

use App\Jobs\SendLifecycleEmailJob;
use App\Mail\AfterFirstMaintenanceLogMail;
use App\Mail\Lifecycle\LifecycleEmailMailable;
use App\Mail\NoMaintenanceLogDay14Mail;
use App\Mail\NoMaintenanceLogDay30Mail;
use App\Mail\NoMaintenanceLogDay3Mail;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class LifecycleEmailService
{
    public function resolveEligibleEmailKey(User $user): ?string
    {
        if ($user->hasUnsubscribedFromLifecycleEmails() || ! $user->vehicles()->exists()) {
            return null;
        }

        if ($this->userHasMaintenanceLogs($user)) {
            return $this->resolveAfterFirstMaintenanceKey($user);
        }

        return $this->resolveNoMaintenanceKey($user);
    }

    public function getActiveTemplate(string $emailKey): ?LifecycleEmailTemplate
    {
        return LifecycleEmailTemplate::query()
            ->where('email_key', $emailKey)
            ->where('is_active', true)
            ->first();
    }

    public function reserveLogFor(User $user, string $emailKey): ?LifecycleEmailLog
    {
        $template = $this->getActiveTemplate($emailKey);

        if (! $template || ! $this->canStillReceive($user, $emailKey)) {
            return null;
        }

        try {
            return LifecycleEmailLog::query()->create([
                'user_id' => $user->getKey(),
                'email_key' => $emailKey,
                'subject' => $template->subject,
                'status' => LifecycleEmailLog::STATUS_QUEUED,
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    public function queueEligibleEmail(User $user): bool
    {
        $emailKey = $this->resolveEligibleEmailKey($user);

        if (! $emailKey) {
            return false;
        }

        $log = $this->reserveLogFor($user, $emailKey);

        if (! $log) {
            return false;
        }

        SendLifecycleEmailJob::dispatch($user->getKey(), $emailKey);

        return true;
    }

    public function canStillReceive(User $user, string $emailKey): bool
    {
        if ($user->hasUnsubscribedFromLifecycleEmails()) {
            return false;
        }

        if ($user->lifecycleEmailLogs()->where('email_key', $emailKey)->where('status', LifecycleEmailLog::STATUS_SENT)->exists()) {
            return false;
        }

        return $this->emailKeyMatchesCurrentState($user, $emailKey);
    }

    public function makeMailable(User $user, LifecycleEmailTemplate $template): LifecycleEmailMailable
    {
        $ctaUrl = $this->trackedCtaUrl($user, $template->email_key);
        $unsubscribeUrl = $this->unsubscribeUrl($user);
        $renderedBody = $this->renderTemplateBody($user, $template);

        return match ($template->email_key) {
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3 => new NoMaintenanceLogDay3Mail($user, $template, $ctaUrl, $unsubscribeUrl, $renderedBody),
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 => new NoMaintenanceLogDay14Mail($user, $template, $ctaUrl, $unsubscribeUrl, $renderedBody),
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30 => new NoMaintenanceLogDay30Mail($user, $template, $ctaUrl, $unsubscribeUrl, $renderedBody),
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG => new AfterFirstMaintenanceLogMail($user, $template, $ctaUrl, $unsubscribeUrl, $renderedBody),
            default => throw new \InvalidArgumentException('Unknown lifecycle email key [' . $template->email_key . '].'),
        };
    }

    public function buildPreviewData(User $user, LifecycleEmailTemplate $template): array
    {
        $renderedBody = $this->renderTemplateBody($user, $template);

        return [
            'previewUser' => $user,
            'template' => $template,
            'bodyHtml' => Str::markdown($renderedBody),
            'ctaDestination' => $this->resolveCtaDestination($user, $template->email_key),
            'ctaUrl' => $this->trackedCtaUrl($user, $template->email_key),
            'unsubscribeUrl' => $this->unsubscribeUrl($user),
            'usesGreetingFallback' => $this->resolveFirstName($user) === null,
        ];
    }

    public function trackedCtaUrl(User $user, string $emailKey): string
    {
        return URL::temporarySignedRoute(
            'lifecycle-emails.click',
            now()->addDays(30),
            [
                'user' => $user->getKey(),
                'emailKey' => $emailKey,
            ],
        );
    }

    public function unsubscribeUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'lifecycle-emails.unsubscribe',
            now()->addDays(30),
            [
                'user' => $user->getKey(),
            ],
        );
    }

    public function resolveCtaDestination(User $user, string $emailKey): string
    {
        $vehicle = $this->firstVehicle($user);

        return match ($emailKey) {
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30 => $vehicle
                ? \App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->getKey()])
                : '/admin',
            default => '/admin',
        };
    }

    public function markLifecycleEmailsUnsubscribed(User $user): void
    {
        if ($user->hasUnsubscribedFromLifecycleEmails()) {
            return;
        }

        $user->forceFill([
            'lifecycle_emails_unsubscribed_at' => now(),
        ])->save();
    }

    public function firstVehicle(User $user): ?Vehicle
    {
        return $user->vehicles()->orderBy('id')->first();
    }

    public function renderTemplateBody(?User $user, LifecycleEmailTemplate $template): string
    {
        $firstName = $this->resolveFirstName($user);

        return preg_replace(
            '/Hoi\s+,/u',
            'Hoi,',
            str_replace('{{ first_name }}', $firstName ?? '', $template->body),
        ) ?? $template->body;
    }

    private function resolveNoMaintenanceKey(User $user): ?string
    {
        $ageInDays = (int) $user->created_at?->startOfDay()->diffInDays(now()->startOfDay()) ?: 0;

        foreach ([
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30 => 30,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 => 14,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3 => 3,
        ] as $emailKey => $threshold) {
            if ($ageInDays < $threshold) {
                continue;
            }

            if (! $this->getActiveTemplate($emailKey)) {
                continue;
            }

            if ($user->lifecycleEmailLogs()->where('email_key', $emailKey)->exists()) {
                continue;
            }

            return $emailKey;
        }

        return null;
    }

    private function resolveAfterFirstMaintenanceKey(User $user): ?string
    {
        $emailKey = LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG;

        if (! $this->getActiveTemplate($emailKey)) {
            return null;
        }

        if ($user->lifecycleEmailLogs()->where('email_key', $emailKey)->exists()) {
            return null;
        }

        $firstMaintenanceCreatedAt = MaintenanceLog::query()
            ->whereHas('vehicle', fn ($query) => $query->where('user_id', $user->getKey()))
            ->oldest('created_at')
            ->first()?->created_at;

        if (! $firstMaintenanceCreatedAt) {
            return null;
        }

        return $firstMaintenanceCreatedAt <= now()->subDays(7)
            ? $emailKey
            : null;
    }

    private function userHasMaintenanceLogs(User $user): bool
    {
        return MaintenanceLog::query()
            ->whereHas('vehicle', fn ($query) => $query->where('user_id', $user->getKey()))
            ->exists();
    }

    private function resolveFirstName(?User $user): ?string
    {
        $name = trim((string) ($user?->name ?? ''));

        if ($name === '') {
            return null;
        }

        return Str::of($name)
            ->before(' ')
            ->trim()
            ->toString() ?: null;
    }

    private function emailKeyMatchesCurrentState(User $user, string $emailKey): bool
    {
        return match ($emailKey) {
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30 => ! $this->userHasMaintenanceLogs($user),
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG => $this->resolveAfterFirstMaintenanceKey($user) === $emailKey,
            default => false,
        };
    }
}
