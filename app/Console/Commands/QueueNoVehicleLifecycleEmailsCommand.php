<?php

namespace App\Console\Commands;

use App\Services\Lifecycle\LifecycleEmailService;
use App\Support\LifecycleMailHealth;
use Illuminate\Console\Command;

class QueueNoVehicleLifecycleEmailsCommand extends Command
{
    protected $signature = 'garagebook:lifecycle:no-vehicle';

    protected $description = 'Queue lifecycle-mails voor geverifieerde gebruikers zonder voertuig na dag 2.';

    public function handle(LifecycleEmailService $service, LifecycleMailHealth $health): int
    {
        try {
            $health->assertReadyForLifecycleDelivery();
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $result = $service->queueNoVehicleUsers();

        $this->line('Gevonden: '.$result['found']);
        $this->line('Queued: '.$result['queued']);
        $this->line('Overgeslagen: '.$result['skipped']);

        return self::SUCCESS;
    }
}
