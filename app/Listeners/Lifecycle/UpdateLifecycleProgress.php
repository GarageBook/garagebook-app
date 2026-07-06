<?php

namespace App\Listeners\Lifecycle;

use App\Models\User;
use App\Services\Lifecycle\LifecycleProgressSyncService;

class UpdateLifecycleProgress
{
    public function __construct(private readonly LifecycleProgressSyncService $syncService) {}

    public function handle(object $event): void
    {
        if (! method_exists($event, 'user')) {
            return;
        }

        $user = $event->user();

        if (! $user instanceof User) {
            return;
        }

        $this->syncService->syncUser($user);
    }
}
