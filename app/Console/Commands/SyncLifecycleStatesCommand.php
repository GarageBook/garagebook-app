<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Lifecycle\LifecycleProgressSyncService;
use Illuminate\Console\Command;

class SyncLifecycleStatesCommand extends Command
{
    protected $signature = 'garagebook:lifecycle:sync-states {--chunk=100 : Aantal users per batch}';

    protected $description = 'Synchroniseert lifecycle states en milestones read-only voor analytics/progress tracking.';

    public function handle(LifecycleProgressSyncService $syncService): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $processed = 0;
        $stateEntriesCreated = 0;
        $stateTransitions = 0;
        $milestonesCreated = 0;

        User::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($users) use ($syncService, &$processed, &$stateEntriesCreated, &$stateTransitions, &$milestonesCreated): void {
                foreach ($users as $user) {
                    $result = $syncService->syncUser($user);
                    $processed++;

                    if ($result['state_created']) {
                        $stateEntriesCreated++;
                    }

                    if ($result['state_transitioned']) {
                        $stateTransitions++;
                    }

                    $milestonesCreated += $result['milestones_created'];
                }
            });

        $this->info('Lifecycle users verwerkt: '.$processed);
        $this->info('Lifecycle state entries aangemaakt: '.$stateEntriesCreated);
        $this->info('Lifecycle state transitions: '.$stateTransitions);
        $this->info('Lifecycle milestones aangemaakt: '.$milestonesCreated);

        return self::SUCCESS;
    }
}
