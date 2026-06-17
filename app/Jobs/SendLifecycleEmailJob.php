<?php

namespace App\Jobs;

use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendLifecycleEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $emailKey,
        public ?int $logId = null,
    ) {}

    public function handle(LifecycleEmailService $service, AnalyticsEventTracker $tracker): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
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
        Mail::to($user->email)->send($service->makeMailable($user, $template, $log));

        $log->forceFill([
            'subject' => $template->subject,
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'skipped_at' => null,
            'reason_skipped' => null,
            'error_message' => null,
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
        ]);
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
            ]) === 1;
    }
}
