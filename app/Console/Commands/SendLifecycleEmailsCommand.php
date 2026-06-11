<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LifecycleEmailService;
use Illuminate\Console\Command;

class SendLifecycleEmailsCommand extends Command
{
    protected $signature = 'garagebook:send-lifecycle-emails';

    protected $description = 'Selecteert lifecycle-mails en queued verzendingen voor relevante GarageBook-users.';

    public function handle(LifecycleEmailService $service): int
    {
        $queued = 0;

        User::query()
            ->whereHas('vehicles')
            ->whereNull('lifecycle_emails_unsubscribed_at')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($service, &$queued): void {
                foreach ($users as $user) {
                    if ($service->queueEligibleEmail($user)) {
                        $queued++;
                    }
                }
            });

        $this->info('Lifecycle e-mails gequeued: ' . $queued);

        return self::SUCCESS;
    }
}
