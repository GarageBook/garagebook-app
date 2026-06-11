<?php

namespace App\Jobs;

use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Support\AnalyticsEventTracker;
use App\Services\LifecycleEmailService;
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
    ) {
    }

    public function handle(LifecycleEmailService $service, AnalyticsEventTracker $tracker): void
    {
        $user = User::query()->find($this->userId);

        if (! $user || $user->hasUnsubscribedFromLifecycleEmails()) {
            return;
        }

        $template = $service->getActiveTemplate($this->emailKey);

        if (! $template || ! $service->canStillReceive($user, $this->emailKey)) {
            return;
        }

        $log = LifecycleEmailLog::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'email_key' => $this->emailKey,
            ],
            [
                'subject' => $template->subject,
                'status' => LifecycleEmailLog::STATUS_QUEUED,
            ],
        );

        Mail::to($user->email)->send($service->makeMailable($user, $template));

        $log->forceFill([
            'subject' => $template->subject,
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        $tracker->queueLifecycleEmailSent($user, $this->emailKey);
    }

    public function failed(\Throwable $exception): void
    {
        LifecycleEmailLog::query()
            ->where('user_id', $this->userId)
            ->where('email_key', $this->emailKey)
            ->update([
                'status' => LifecycleEmailLog::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => str($exception->getMessage())->limit(65535)->value(),
            ]);
    }
}
