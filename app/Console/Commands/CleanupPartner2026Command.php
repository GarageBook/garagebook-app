<?php

namespace App\Console\Commands;

use App\Services\Growth\Partner2026\Partner2026CleanupService;
use Illuminate\Console\Command;

class CleanupPartner2026Command extends Command
{
    protected $signature = 'garagebook:partner2026-cleanup';

    protected $description = 'Corrigeer Partner2026 prospect-readiness zonder te mailen of te queuen.';

    public function handle(Partner2026CleanupService $service): int
    {
        $result = $service->cleanup();
        $attentionRecords = $service->attentionRecords();

        $this->info('Partner2026 cleanup voltooid.');
        $this->line('ready: '.$result['ready']);
        $this->line('needs email: '.$result['needs_email']);
        $this->line('invalid email: '.$result['invalid_email']);
        $this->line('manual review: '.$result['manual_review']);
        $this->line('duplicates: '.$result['duplicates']);
        $this->line('updated: '.$result['updated']);
        $this->newLine();
        $this->table(
            ['Naam', 'E-mailstatus', 'Lifecycle', 'Skip reason', 'Website', 'Duplicate'],
            $attentionRecords->map(fn ($record): array => [
                $record->name,
                $record->email_status,
                $record->lifecycle_status,
                $record->skip_reason ?? '-',
                $record->website ?? '-',
                $record->duplicate_of_id ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
