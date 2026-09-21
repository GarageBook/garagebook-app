<?php

namespace App\Console\Commands;

use App\Models\LifecycleEmailLog;
use App\Services\LifecycleEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RecoverFailedLifecycleEmailsCommand extends Command
{
    protected $signature = 'garagebook:recover-failed-lifecycle-emails
        {--from= : Ondergrens voor failed_at}
        {--to= : Bovengrens voor failed_at}
        {--email-key=* : Optionele lifecycle email_key-filter(s)}
        {--ids= : Optionele kommagescheiden lifecycle-log-ids}
        {--execute : Maak een nieuwe recovery-log en queue de mail}
        {--confirm : Verplichte bevestiging naast --execute}';

    protected $description = 'Inspecteert en herstelt optioneel failed lifecycle-mails na actuele eligibilitycontrole. Standaard dry-run.';

    public function handle(LifecycleEmailService $service): int
    {
        $from = $this->parseDateOption('from');
        $to = $this->parseDateOption('to');

        if (! $from || ! $to) {
            return self::FAILURE;
        }

        if ($from->gt($to)) {
            $this->error('--from moet vóór --to liggen.');

            return self::FAILURE;
        }

        if ($this->option('execute') && ! $this->option('confirm')) {
            $this->error('--execute vereist ook --confirm.');

            return self::FAILURE;
        }

        $query = LifecycleEmailLog::query()
            ->where('status', LifecycleEmailLog::STATUS_FAILED)
            ->whereBetween('failed_at', [$from, $to])
            ->orderBy('id');

        $emailKeys = array_values(array_filter((array) $this->option('email-key')));

        if ($emailKeys !== []) {
            $query->whereIn('email_key', $emailKeys);
        }

        if ($ids = $this->parseIds()) {
            $query->whereKey($ids);
        }

        $logs = $query->get();
        $rows = [];
        $counts = ['recoverable' => 0, 'ambiguous' => 0, 'no_longer_eligible' => 0];

        foreach ($logs as $log) {
            $classification = $service->classifyFailedRecovery($log);
            $counts[$classification['category']]++;

            $result = ['status' => 'dry-run', 'reason' => $classification['reason']];

            if ($this->option('execute') && $classification['category'] === 'recoverable') {
                $result = $service->recoverFailedLog($log);

                Log::info('lifecycle_failed_recovery', [
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
                $classification['category'],
                $classification['reason'],
                $result['status'],
                $result['retry_log_id'] ?? '-',
            ];
        }

        $this->line('Mode: '.($this->option('execute') ? 'execute' : 'dry-run'));
        $this->line('From: '.$from->toDateTimeString());
        $this->line('To: '.$to->toDateTimeString());
        $this->line('Recoverable: '.$counts['recoverable']);
        $this->line('Ambiguous: '.$counts['ambiguous']);
        $this->line('No longer eligible: '.$counts['no_longer_eligible']);
        $this->table(
            ['id', 'user_id', 'email_key', 'classification', 'reason', 'result', 'retry_log_id'],
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
