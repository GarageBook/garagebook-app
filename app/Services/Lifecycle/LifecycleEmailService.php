<?php

namespace App\Services\Lifecycle;

use App\Jobs\SendLifecycleEmailJob;
use App\Mail\Lifecycle\NoVehicleDay2Mail;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use Illuminate\Database\QueryException;

class LifecycleEmailService
{
    public function queueNoVehicleUsers(): array
    {
        $found = 0;
        $queued = 0;
        $skipped = 0;

        $this->eligibleNoVehicleUsersQuery()
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$found, &$queued, &$skipped): void {
                foreach ($users as $user) {
                    $found++;
                    $log = $this->createQueuedNoVehicleLog($user);

                    if (! $log) {
                        $skipped++;

                        continue;
                    }

                    SendLifecycleEmailJob::dispatch(
                        $user->getKey(),
                        LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2,
                        $log->getKey(),
                    );

                    $queued++;
                }
            });

        return [
            'found' => $found,
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    private function eligibleNoVehicleUsersQuery()
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->where('created_at', '<=', now()->subDays(2))
            ->whereNull('lifecycle_emails_unsubscribed_at')
            ->whereDoesntHave('vehicles')
            ->whereDoesntHave('lifecycleEmailLogs', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('trigger', LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2)
                        ->orWhere('email_key', LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2);
                });
            });
    }

    private function createQueuedNoVehicleLog(User $user): ?LifecycleEmailLog
    {
        try {
            return LifecycleEmailLog::query()->create([
                'user_id' => $user->getKey(),
                'email_key' => LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2,
                'trigger' => LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2,
                'subject' => 'Je GarageBook is nog een beetje leeg... 😉',
                'mail_class' => NoVehicleDay2Mail::class,
                'status' => LifecycleEmailLog::STATUS_QUEUED,
                'queued_at' => now(),
                'sent_at' => null,
                'failed_at' => null,
                'skipped_at' => null,
                'error' => null,
                'error_message' => null,
                'reason_skipped' => null,
                'vehicles_count' => 0,
                'maintenance_logs_count' => 0,
                'documents_count' => 0,
                'last_login_at' => $user->last_login_at,
            ]);
        } catch (QueryException) {
            return null;
        }
    }
}
