<?php

namespace App\Console\Commands;

use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\LifecycleEmailService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RetryLifecycleEmailsCommand extends Command
{
    protected $signature = 'garagebook:retry-lifecycle-emails
        {--before= : Selecteer alleen sent lifecycle-logs van voor deze timestamp}
        {--execute : Verstuur de geselecteerde lifecycle-mails echt opnieuw}
        {--ignore-eligibility : Verstuur ook naar users die nu unsubscribed of niet meer eligible zijn}
        {--reset-failed-retries : Reset alleen mislukte retry-markeringen op originele lifecycle-logs zodat ze opnieuw eligible worden}';

    protected $description = 'Voert een eenmalige retry uit voor geselecteerde lifecycle-mails die historisch onterecht als sent zijn gelogd.';

    public function handle(LifecycleEmailService $service, \App\Support\LifecycleEmailRetryThrottle $throttle): int
    {
        $before = $this->parseBeforeOption();

        if (! $before) {
            return self::FAILURE;
        }

        $ignoreEligibility = (bool) $this->option('ignore-eligibility');
        $execute = (bool) $this->option('execute');
        $resetFailedRetries = (bool) $this->option('reset-failed-retries');

        if ($execute) {
            try {
                $service->assertMailDeliveryStackReady();
            } catch (\RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if ($resetFailedRetries) {
            $resetCount = $this->resetFailedRetries($service, $before);
            $this->info('Gefaalde retry-markeringen gereset: ' . $resetCount);
            $this->newLine();
        }

        $selectedCount = $this->retryBaseQuery($service, $before)->count();
        $countsPerEmailKey = $this->retryBaseQuery($service, $before)
            ->selectRaw('email_key, COUNT(*) as aggregate')
            ->groupBy('email_key')
            ->orderBy('email_key')
            ->pluck('aggregate', 'email_key')
            ->all();

        $dryRunRows = $this->retryBaseQuery($service, $before)
            ->with('user')
            ->orderBy('id')
            ->limit(10)
            ->get();

        $summary = $this->buildSummary($service, $before, $ignoreEligibility);

        $this->line('Mode: ' . ($execute ? 'execute' : 'dry-run'));
        $this->line('Before: ' . $before->format('Y-m-d H:i:s'));
        $this->line('Ignore eligibility: ' . ($ignoreEligibility ? 'yes' : 'no'));
        $this->line('Reset failed retries: ' . ($resetFailedRetries ? 'yes' : 'no'));
        $this->newLine();
        $this->info('Geselecteerde logs: ' . $selectedCount);

        foreach ($countsPerEmailKey as $emailKey => $count) {
            $this->line($emailKey . ': ' . $count);
        }

        $this->newLine();
        $this->line('Eligible voor retry nu: ' . $summary['ready']);
        $this->line('Overgeslagen door unsubscribe: ' . $summary['unsubscribed']);
        $this->line('Overgeslagen door ineligibility: ' . $summary['no_longer_eligible']);

        if ($summary['other_skips'] > 0) {
            $this->line('Overige skips: ' . $summary['other_skips']);
        }

        $this->newLine();
        $this->line('Eerste 10 logs:');
        $this->table(
            ['original_log_id', 'user_id', 'email', 'email_key', 'retry_status_preview'],
            $dryRunRows->map(function (LifecycleEmailLog $log) use ($service, $ignoreEligibility): array {
                $user = User::query()->find($log->user_id);

                return [
                    'original_log_id' => $log->getKey(),
                    'user_id' => $log->user_id,
                    'email' => $user?->email ?? '-',
                    'email_key' => $log->email_key,
                    'retry_status_preview' => $service->retryStatusForLog($log, $user, $ignoreEligibility),
                ];
            })->all()
        );

        if (! $execute) {
            $this->comment('Dry-run afgerond. Geen mails verstuurd.');

            return self::SUCCESS;
        }

        $processed = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];
        $sendAttempts = 0;

        $this->retryBaseQuery($service, $before)
            ->orderBy('id')
            ->chunkById(100, function ($logs) use ($service, $ignoreEligibility, $throttle, &$processed, &$sendAttempts): void {
                foreach ($logs as $log) {
                    $result = $service->retryLifecycleEmailLog(
                        $log,
                        $ignoreEligibility,
                        function () use ($throttle, &$sendAttempts): void {
                            $throttle->pauseBeforeSend($sendAttempts);
                            $sendAttempts++;
                        },
                    );
                    $status = $result['status'];
                    $errorMessage = $result['error_message'];
                    $retryLogId = $result['retry_log_id'];

                    if ($status === LifecycleEmailLog::STATUS_SENT) {
                        $processed['sent']++;
                    } elseif ($status === LifecycleEmailLog::STATUS_FAILED) {
                        $processed['failed']++;
                    } else {
                        $processed['skipped']++;
                    }

                    $context = [
                        'original_log_id' => $log->getKey(),
                        'retry_log_id' => $retryLogId,
                        'user_id' => $log->user_id,
                        'email_key' => $log->email_key,
                        'status' => $status,
                        'error_message' => $errorMessage,
                    ];

                    Log::info('lifecycle_retry_result', $context);
                    $this->line(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            });

        $this->newLine();
        $this->info('Retry sent: ' . $processed['sent']);
        $this->line('Retry failed: ' . $processed['failed']);
        $this->line('Retry skipped: ' . $processed['skipped']);

        return self::SUCCESS;
    }

    private function parseBeforeOption(): ?Carbon
    {
        $before = $this->option('before');

        if (! is_string($before) || trim($before) === '') {
            $this->error('Optie --before="YYYY-MM-DD HH:MM:SS" is verplicht.');

            return null;
        }

        try {
            return Carbon::parse($before);
        } catch (\Throwable) {
            $this->error('Ongeldige --before timestamp: ' . $before);

            return null;
        }
    }

    private function retryBaseQuery(LifecycleEmailService $service, Carbon $before): Builder
    {
        return LifecycleEmailLog::query()
            ->where('status', LifecycleEmailLog::STATUS_SENT)
            ->whereIn('email_key', $service->retryableEmailKeys())
            ->where('email_key', 'not like', 'test_%')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('retry_status')
                    ->orWhere('retry_status', LifecycleEmailLog::STATUS_FAILED);
            })
            ->whereRaw('COALESCE(sent_at, created_at) < ?', [$before->format('Y-m-d H:i:s')]);
    }

    private function buildSummary(LifecycleEmailService $service, Carbon $before, bool $ignoreEligibility): array
    {
        $summary = [
            'ready' => 0,
            'unsubscribed' => 0,
            'no_longer_eligible' => 0,
            'other_skips' => 0,
        ];

        $this->retryBaseQuery($service, $before)
            ->orderBy('id')
            ->chunkById(100, function ($logs) use ($service, $ignoreEligibility, &$summary): void {
                foreach ($logs as $log) {
                    $status = $service->retryStatusForLog($log, User::query()->find($log->user_id), $ignoreEligibility);

                    match ($status) {
                        'ready' => $summary['ready']++,
                        'unsubscribed' => $summary['unsubscribed']++,
                        'no_longer_eligible' => $summary['no_longer_eligible']++,
                        default => $summary['other_skips']++,
                    };
                }
            });

        return $summary;
    }

    private function resetFailedRetries(LifecycleEmailService $service, Carbon $before): int
    {
        return LifecycleEmailLog::query()
            ->where('status', LifecycleEmailLog::STATUS_SENT)
            ->whereIn('email_key', $service->retryableEmailKeys())
            ->where('email_key', 'not like', 'test_%')
            ->where('retry_status', LifecycleEmailLog::STATUS_FAILED)
            ->whereRaw('COALESCE(sent_at, created_at) < ?', [$before->format('Y-m-d H:i:s')])
            ->update([
                'retried_at' => null,
            ]);
    }
}
