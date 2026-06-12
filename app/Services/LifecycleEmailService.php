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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LifecycleEmailService
{
    public function retryableEmailKeys(): array
    {
        return [
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30,
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
        ];
    }

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

    public function sendTestMail(User $user, LifecycleEmailTemplate $template): LifecycleEmailLog
    {
        Log::info('lifecycle_test_mail_start', [
            'user_id' => $user->getKey(),
            'template_email_key' => $template->email_key,
            'email' => $user->email,
        ]);

        $log = $this->createTestLog($user, $template);

        if (! $log->exists || blank($log->getKey())) {
            Log::error('lifecycle_test_mail_log_missing_after_create', [
                'user_id' => $user->getKey(),
                'template_email_key' => $template->email_key,
            ]);

            throw new RuntimeException('Testmail loggen mislukt: geen logrecord-id ontvangen.');
        }

        try {
            $log = $this->sendLifecycleMailForLog(
                user: $user,
                template: $template,
                log: $log,
                failurePrefix: 'Testmail verzenden mislukt',
            );

            Log::info('lifecycle_test_mail_send_success', [
                'log_id' => $log->getKey(),
                'email_key' => $log->email_key,
                'user_id' => $user->getKey(),
            ]);

            return $log;
        } catch (RuntimeException $exception) {
            Log::error('lifecycle_test_mail_exception', [
                'log_id' => $log->getKey(),
                'email_key' => $log->email_key,
                'user_id' => $user->getKey(),
                'message' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
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

    public function makeTestEmailKey(string $templateEmailKey): string
    {
        return 'test_' . $templateEmailKey . '_' . now()->format('YmdHisv');
    }

    public function makeRetryEmailKey(LifecycleEmailLog $originalLog): string
    {
        return 'retry_'
            . $originalLog->email_key
            . '_'
            . now()->format('YmdHisv')
            . '_'
            . $originalLog->getKey()
            . '_'
            . Str::lower((string) Str::ulid());
    }

    public function assertMailDeliveryStackReady(): void
    {
        $this->assertMailConfigurationIsUsable();

        $defaultMailer = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$defaultMailer}.transport");

        if ($transport === 'resend' && ! class_exists(\Resend\Resend::class)) {
            throw new RuntimeException('Mailconfig resend is actief maar resend/resend-php ontbreekt. Draai composer install of composer require resend/resend-php.');
        }
    }

    public function retryStatusForLog(LifecycleEmailLog $log, ?User $user, bool $ignoreEligibility = false): string
    {
        if ($log->retry_status === LifecycleEmailLog::STATUS_SENT) {
            return 'already_retried';
        }

        if (! in_array($log->email_key, $this->retryableEmailKeys(), true)) {
            return 'email_key_not_allowed';
        }

        if (str_starts_with($log->email_key, 'test_')) {
            return 'test_log';
        }

        if ($log->status !== LifecycleEmailLog::STATUS_SENT) {
            return 'status_not_sent';
        }

        if (! $user) {
            return 'user_missing';
        }

        if ($user->hasUnsubscribedFromLifecycleEmails() && ! $ignoreEligibility) {
            return 'unsubscribed';
        }

        if (! $ignoreEligibility && ! $this->emailKeyMatchesCurrentState($user, $log->email_key)) {
            return 'no_longer_eligible';
        }

        if (! $this->getActiveTemplate($log->email_key)) {
            return 'template_inactive';
        }

        return 'ready';
    }

    public function retryLifecycleEmailLog(LifecycleEmailLog $originalLog, bool $ignoreEligibility = false): array
    {
        $prepared = DB::transaction(function () use ($originalLog, $ignoreEligibility): array {
            $lockedLog = LifecycleEmailLog::query()
                ->lockForUpdate()
                ->find($originalLog->getKey());

            if (! $lockedLog) {
                return [
                    'status' => 'missing',
                    'retry_log_id' => null,
                    'error_message' => 'Origineel lifecycle-log bestaat niet meer.',
                ];
            }

            $user = User::query()->find($lockedLog->user_id);
            $status = $this->retryStatusForLog($lockedLog, $user, $ignoreEligibility);

            if ($status !== 'ready') {
                return [
                    'status' => $status,
                    'retry_log_id' => null,
                    'error_message' => null,
                ];
            }

            $template = $this->getActiveTemplate($lockedLog->email_key);

            if (! $template || ! $user) {
                return [
                    'status' => 'template_inactive',
                    'retry_log_id' => null,
                    'error_message' => 'Geen actieve lifecycle-template gevonden voor retry.',
                ];
            }

            $retryLog = LifecycleEmailLog::query()->create([
                'user_id' => $user->getKey(),
                'email_key' => $this->makeRetryEmailKey($lockedLog),
                'subject' => $template->subject,
                'status' => LifecycleEmailLog::STATUS_QUEUED,
            ]);

            return [
                'status' => 'ready',
                'locked_log_id' => $lockedLog->getKey(),
                'retry_log_id' => $retryLog->getKey(),
                'user_id' => $user->getKey(),
                'email_key' => $lockedLog->email_key,
            ];
        });

        if ($prepared['status'] !== 'ready') {
            return [
                'status' => $prepared['status'],
                'retry_log_id' => $prepared['retry_log_id'] ?? null,
                'error_message' => $prepared['error_message'] ?? null,
            ];
        }

        $lockedLog = LifecycleEmailLog::query()->findOrFail($prepared['locked_log_id']);
        $retryLog = LifecycleEmailLog::query()->findOrFail($prepared['retry_log_id']);
        $user = User::query()->find($prepared['user_id']);
        $template = $this->getActiveTemplate($prepared['email_key']);

        if (! $user || ! $template) {
            return [
                'status' => 'template_inactive',
                'retry_log_id' => $retryLog->getKey(),
                'error_message' => 'Geen actieve lifecycle-template gevonden voor retry.',
            ];
        }

        try {
            $retryLog = $this->sendLifecycleMailForLog(
                user: $user,
                template: $template,
                log: $retryLog,
                failurePrefix: 'Retry lifecycle-email verzenden mislukt',
            );
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            DB::transaction(function () use ($lockedLog, $retryLog, $message): void {
                LifecycleEmailLog::query()
                    ->whereKey($lockedLog->getKey())
                    ->update([
                        'retried_at' => null,
                        'retry_status' => LifecycleEmailLog::STATUS_FAILED,
                        'retry_log_id' => $retryLog->getKey(),
                        'retry_error_message' => Str::limit($message, 65535, ''),
                    ]);
            });

            return [
                'status' => LifecycleEmailLog::STATUS_FAILED,
                'retry_log_id' => $retryLog->getKey(),
                'error_message' => $message,
            ];
        }

        DB::transaction(function () use ($lockedLog, $retryLog): void {
            LifecycleEmailLog::query()
                ->whereKey($lockedLog->getKey())
                ->update([
                    'retried_at' => now(),
                    'retry_status' => LifecycleEmailLog::STATUS_SENT,
                    'retry_log_id' => $retryLog->getKey(),
                    'retry_error_message' => null,
                ]);
        });

        return [
            'status' => LifecycleEmailLog::STATUS_SENT,
            'retry_log_id' => $retryLog->getKey(),
            'error_message' => null,
        ];
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

        if (! $this->userQualifiesForAfterFirstMaintenanceEmail($user)) {
            return null;
        }

        if ($user->lifecycleEmailLogs()->where('email_key', $emailKey)->exists()) {
            return null;
        }

        return $emailKey;
    }

    private function userQualifiesForAfterFirstMaintenanceEmail(User $user): bool
    {
        $emailKey = LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG;

        if (! $this->getActiveTemplate($emailKey)) {
            return false;
        }

        $firstMaintenanceCreatedAt = MaintenanceLog::query()
            ->whereHas('vehicle', fn ($query) => $query->where('user_id', $user->getKey()))
            ->oldest('created_at')
            ->first()?->created_at;

        if (! $firstMaintenanceCreatedAt) {
            return false;
        }

        return $firstMaintenanceCreatedAt <= now()->subDays(7);
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

    private function createTestLog(User $user, LifecycleEmailTemplate $template): LifecycleEmailLog
    {
        Log::info('lifecycle_test_mail_before_log_create', [
            'user_id' => $user->getKey(),
            'template_email_key' => $template->email_key,
        ]);

        try {
            $log = LifecycleEmailLog::query()->create([
                'user_id' => $user->getKey(),
                'email_key' => $this->makeTestEmailKey($template->email_key),
                'subject' => $template->subject,
                'status' => LifecycleEmailLog::STATUS_QUEUED,
            ]);
        } catch (QueryException $exception) {
            Log::error('lifecycle_test_mail_log_create_exception', [
                'user_id' => $user->getKey(),
                'template_email_key' => $template->email_key,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Testmail loggen mislukt voordat de mail kon worden verstuurd.', previous: $exception);
        }

        Log::info('lifecycle_test_mail_after_log_create', [
            'log_id' => $log->getKey(),
            'email_key' => $log->email_key,
            'user_id' => $user->getKey(),
        ]);

        return $log;
    }

    private function sendLifecycleMailForLog(
        User $user,
        LifecycleEmailTemplate $template,
        LifecycleEmailLog $log,
        string $failurePrefix,
    ): LifecycleEmailLog {
        try {
            $this->assertMailDeliveryStackReady();
            Mail::to($user->email)->send($this->makeMailable($user, $template));

            $log->forceFill([
                'subject' => $template->subject,
                'status' => LifecycleEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            return $log->fresh() ?? $log;
        } catch (Throwable $exception) {
            $message = $this->formatLifecycleMailErrorMessage($failurePrefix, $exception);

            rescue(
                fn () => $log->forceFill([
                    'subject' => $template->subject,
                    'status' => LifecycleEmailLog::STATUS_FAILED,
                    'sent_at' => null,
                    'failed_at' => now(),
                    'error_message' => Str::limit($message, 65535, ''),
                ])->save(),
                report: false,
            );

            throw new RuntimeException($message, previous: $exception);
        }
    }

    private function assertMailConfigurationIsUsable(): void
    {
        $defaultMailer = config('mail.default');

        if (blank($defaultMailer)) {
            throw new RuntimeException('Mailconfig ontbreekt: mail.default is niet ingesteld.');
        }

        $transport = config("mail.mailers.{$defaultMailer}.transport");

        if (blank($transport)) {
            throw new RuntimeException("Mailconfig ontbreekt: mailer [{$defaultMailer}] heeft geen transport.");
        }
    }

    private function formatLifecycleMailErrorMessage(string $failurePrefix, Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            return $failurePrefix . ' zonder foutmelding vanuit de mailer.';
        }

        return $failurePrefix . ': ' . $message;
    }

    private function emailKeyMatchesCurrentState(User $user, string $emailKey): bool
    {
        return match ($emailKey) {
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30 => ! $this->userHasMaintenanceLogs($user),
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG => $this->userQualifiesForAfterFirstMaintenanceEmail($user),
            default => false,
        };
    }
}
