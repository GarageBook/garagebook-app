<?php

namespace App\Console\Commands;

use App\Services\Growth\Partner2026\Partner2026CleanupService;
use Illuminate\Console\Command;

class QualityFilterPartner2026Command extends Command
{
    protected $signature = 'garagebook:partner2026-quality-filter';

    protected $description = 'Pas Partner2026 quality filtering toe zonder te mailen of te queuen.';

    public function handle(Partner2026CleanupService $service): int
    {
        $result = $service->cleanup();

        $this->info('Partner2026 quality filter voltooid.');
        $this->line('ready: '.$result['ready']);
        $this->line('needs email: '.$result['needs_email']);
        $this->line('invalid email: '.$result['invalid_email']);
        $this->line('manual review: '.$result['manual_review']);
        $this->line('duplicates: '.$result['duplicates']);
        $this->line('updated: '.$result['updated']);
        $this->warn('Er is niets gemaild en niets gequeued.');

        return self::SUCCESS;
    }
}
