<?php

namespace App\Console\Commands;

use App\Models\LifecycleEmailLog;
use App\Services\LifecycleEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RecoverStaleLifecycleEmailsCommand extends Command
{
    protected $signature = 'garagebook:recover-stale-lifecycle-emails
        {--before= : Alleen queued/processing logs die vóór deze timestamp voor het laatst zijn gewijzigd}
        {--ids= : Optionele kommagescheiden lifecycle-log-ids}
        {--execute : Zet recoverable logs opnieuw in de queue}
        {--confirm : Verplichte bevestiging naast --execute}
        {--include-processing : Sta expliciet herstel toe van ambigue processing-logs}';

    protected $description = 'Inspecteert en herstelt optioneel stale queued/processing lifecycle-mails. Standaard dry-run.';

    public function handle(LifecycleEmailService $service): int
    {
        $before = $this->parseDateOption('before');

        if (! $before) {
            return self::FAILURE;
        }

        if ($this->option('execute') && ! $this->option('confirm')) {
            $this->error('--execute vereist ook --confirm.');

            return self::FAILURE;
        }

        $query = LifecycleEmailLog::query()
            ->whereIn('status', [LifecycleEmailLog::STATUS_QUEUED, LifecycleEmailLog::STATUS_PROCESSING])
            ->where('updated_at', '<=', $before)
            ->orderBy('id');

        if ($ids = $this->parseIds()) {
            $query->whereKey($ids);
        }

        $logs = $query->get();
        $rows = [];
        $counts = ['recoverable' => 0, 'ambiguous' => 0, 'no_longer_eligible' => 0];

        foreach ($logs as $log) {
            $classification = $service->classifyStaleRecovery($log);
            $counts[$classification['category']]++;

            $result = ['status' => 'dry-run', 'reason' => $classification['reason']];

            if ($this->option('execute') && (
                $classification['category'] === 'recoverable'
                || ($classification['category'] === 'ambiguous' && $this->option('include-processing'))
            )) {
                $result = $service->recoverStaleLog(
                    $log,
                    $before,
                    (bool) $this->option('include-processing'),
                );

                Log::info('lifecycle_stale_recovery', [
                    'log_id' => $log->getKey(),
                    'user_id' => $log->user_id,
                    'email_key' => $log->baseEmailKey(),
                    ...$classification,
                    ...$result,
                ]);
            }

            $rows[] = [
                $log->getKey(),
                $log->user_id,
                $log->baseEmailKey(),
                $log->status,
                $classification['category'],
                $classification['reason'],
                $result['status'],
            ];
        }

        $this->line('Mode: '.($this->option('execute') ? 'execute' : 'dry-run'));
        $this->line('Before: '.$before->toDateTimeString());
        $this->line('Recoverable: '.$counts['recoverable']);
        $this->line('Ambiguous: '.$counts['ambiguous']);
        $this->line('No longer eligible: '.$counts['no_longer_eligible']);
        $this->table(
            ['id', 'user_id', 'email_key', 'status', 'classification', 'reason', 'result'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function parseDateOption(string $name): ?Carbon
    {
        $value = $this->option($name);

        if (! is_string($value) || blank($value)) {
            $this->error("--{$name} is verplicht.");

            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            $this->error("Ongeldige --{$name} timestamp.");

            return null;
        }
    }

    /**
     * @return array<int>
     */
    private function parseIds(): array
    {
        return collect(explode(',', (string) $this->option('ids')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->values()
            ->all();
    }
}
