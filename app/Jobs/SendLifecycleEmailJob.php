<?php

namespace App\Jobs;

use App\Mail\Lifecycle\NoVehicleDay2Mail;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use App\Support\LifecycleMailHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLifecycleEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(
        public int $userId,
        public string $emailKey,
        public ?int $logId = null,
    ) {}

    public function middleware(): array
    {
        return [
            (new RateLimited('lifecycle-email'))->releaseAfter(1),
        ];
    }

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(LifecycleEmailService $service, AnalyticsEventTracker $tracker, ?LifecycleMailHealth $health = null): void
    {
        $health ??= app(LifecycleMailHealth::class);
        $health->assertReadyForLifecycleDelivery();

        $user = User::query()->find($this->userId);

        if (! $user) {
            $this->markMissingUserSkipped();

            return;
        }

        $template = $service->getActiveTemplate($this->emailKey);
        $log = $this->resolveLog($template);

        if (! $log) {
            return;
        }

        if (in_array($log->status, [LifecycleEmailLog::STATUS_SENT, LifecycleEmailLog::STATUS_FAILED, LifecycleEmailLog::STATUS_SKIPPED], true)) {
            return;
        }

        if (! $this->claimQueuedLog($log)) {
            return;
        }

        $log->refresh();

        if ($this->isNoVehicleDay2Log($log)) {
            $this->sendNoVehicleDay2Mail($user, $log, $service, $health);

            return;
        }

        if ($user->hasUnsubscribedFromLifecycleEmails()) {
            $service->markLifecycleEmailSkipped($user, $this->emailKey, 'unsubscribed');

            return;
        }

        if (! $template) {
            $service->markLifecycleEmailSkipped($user, $this->emailKey, 'template_inactive');

            return;
        }

        $reason = $service->resolveSkipReason($user, $this->emailKey);

        if ($reason !== null) {
            $service->markLifecycleEmailSkipped($user, $this->emailKey, $reason);

            return;
        }

        $service->assertMailDeliveryStackReady();
        $sentMessage = Mail::to($user->email)->send($service->makeMailable($user, $template, $log));

        $log->forceFill([
            'subject' => $template->subject,
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'skipped_at' => null,
            'reason_skipped' => null,
            'error_message' => null,
            ...LifecycleEmailLog::existingColumnAttributes($health->logContext(
                queueJobId: $this->queueJobId(),
                resendMessageId: $this->resolveSentMessageId($sentMessage),
            )),
        ])->save();

        $tracker->queueLifecycleEmailSent($user, $this->emailKey);
    }

    public function failed(\Throwable $exception): void
    {
        $query = LifecycleEmailLog::query();

        if ($this->logId) {
            $query->whereKey($this->logId);
        } else {
            $query
                ->where('user_id', $this->userId)
                ->where('email_key', $this->emailKey);
        }

        $query->update([
            'status' => LifecycleEmailLog::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => str($exception->getMessage())->limit(65535)->value(),
            'error' => str($exception->getMessage())->limit(65535)->value(),
            ...LifecycleEmailLog::existingColumnAttributes(app(LifecycleMailHealth::class)->logContext(
                queueJobId: $this->queueJobId(),
            )),
        ]);
    }

    private function markMissingUserSkipped(): void
    {
        if (! $this->logId) {
            return;
        }

        LifecycleEmailLog::query()
            ->whereKey($this->logId)
            ->whereIn('status', [LifecycleEmailLog::STATUS_QUEUED, LifecycleEmailLog::STATUS_PROCESSING])
            ->update([
                'status' => LifecycleEmailLog::STATUS_SKIPPED,
                'skipped_at' => now(),
                'reason_skipped' => 'user_missing',
                'error' => 'user_missing',
                'error_message' => 'user_missing',
            ]);
    }

    private function isNoVehicleDay2Log(LifecycleEmailLog $log): bool
    {
        return $log->trigger === LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2
            || $log->email_key === LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2
            || $log->mail_class === NoVehicleDay2Mail::class;
    }

    private function sendNoVehicleDay2Mail(User $user, LifecycleEmailLog $log, LifecycleEmailService $service, LifecycleMailHealth $health): void
    {
        if ($user->vehicles()->exists()) {
            $log->forceFill([
                'status' => LifecycleEmailLog::STATUS_SKIPPED,
                'skipped_at' => now(),
                'reason_skipped' => 'vehicle_added',
                'error' => 'vehicle_added',
                'error_message' => 'vehicle_added',
            ])->save();

            return;
        }

        try {
            $sentMessage = Mail::to($user->email)->send(new NoVehicleDay2Mail(
                user: $user,
                ctaUrl: url('/admin/vehicles/create'),
                unsubscribeUrl: $service->unsubscribeUrl($user),
            ));

            $log->forceFill([
                'status' => LifecycleEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'skipped_at' => null,
                'reason_skipped' => null,
                'error' => null,
                'error_message' => null,
                ...LifecycleEmailLog::existingColumnAttributes($health->logContext(
                    queueJobId: $this->queueJobId(),
                    resendMessageId: $this->resolveSentMessageId($sentMessage),
                )),
            ])->save();
        } catch (\Throwable $exception) {
            if ($this->isRateLimitException($exception)) {
                Log::warning('lifecycle_mail_rate_limited', [
                    'log_id' => $log->getKey(),
                    'user_id' => $user->getKey(),
                    'email_key' => $log->email_key,
                    'attempt' => $this->attempts(),
                    'message' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            $log->forceFill([
                'status' => LifecycleEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error' => str($exception->getMessage())->limit(65535)->value(),
                'error_message' => str($exception->getMessage())->limit(65535)->value(),
                ...LifecycleEmailLog::existingColumnAttributes($health->logContext(
                    queueJobId: $this->queueJobId(),
                )),
            ])->save();
        }
    }

    private function queueJobId(): ?int
    {
        if (property_exists($this, 'job') && $this->job && method_exists($this->job, 'getJobId')) {
            $jobId = $this->job->getJobId();

            return is_numeric($jobId) ? (int) $jobId : null;
        }

        return null;
    }

    private function resolveSentMessageId(mixed $sentMessage): ?string
    {
        if (is_object($sentMessage) && method_exists($sentMessage, 'getMessageId')) {
            return $sentMessage->getMessageId();
        }

        return null;
    }

    private function isRateLimitException(\Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof \Throwable) {
            $message = strtolower($current->getMessage());

            if (str_contains($message, 'too many requests') || str_contains($message, '429') || str_contains($message, 'rate limit')) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function resolveLog(?LifecycleEmailTemplate $template): ?LifecycleEmailLog
    {
        if ($this->logId) {
            $log = LifecycleEmailLog::query()->find($this->logId);

            if (! $log) {
                return null;
            }

            if ($log->user_id !== $this->userId || $log->email_key !== $this->emailKey) {
                return null;
            }

            return $log;
        }

        return LifecycleEmailLog::query()->firstOrCreate(
            [
                'user_id' => $this->userId,
                'email_key' => $this->emailKey,
            ],
            [
                'subject' => $template?->subject ?? $this->emailKey,
                'status' => LifecycleEmailLog::STATUS_QUEUED,
                'queued_at' => now(),
            ],
        );
    }

    private function claimQueuedLog(LifecycleEmailLog $log): bool
    {
        if ($log->status === LifecycleEmailLog::STATUS_PROCESSING) {
            return false;
        }

        return LifecycleEmailLog::query()
            ->whereKey($log->getKey())
            ->where('status', LifecycleEmailLog::STATUS_QUEUED)
            ->update([
                'status' => LifecycleEmailLog::STATUS_PROCESSING,
                'failed_at' => null,
                'skipped_at' => null,
                'reason_skipped' => null,
                'error_message' => null,
                'error' => null,
            ]) === 1;
    }
}
