<?php

namespace App\Services\Lifecycle;

use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\LifecycleEmailService as TemplateLifecycleEmailService;

class LifecycleEmailService
{
    public function __construct(private ?TemplateLifecycleEmailService $templateLifecycleEmailService = null) {}

    public function queueNoVehicleUsers(): array
    {
        $found = 0;
        $queued = 0;
        $skipped = 0;
        $service = $this->templateLifecycleEmailService ?? app(TemplateLifecycleEmailService::class);

        $this->eligibleNoVehicleUsersQuery()
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$found, &$queued, &$skipped, $service): void {
                foreach ($users as $user) {
                    $found++;

                    if ($service->queueEligibleEmail($user)) {
                        $queued++;

                        continue;
                    }

                    $skipped++;
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
}
