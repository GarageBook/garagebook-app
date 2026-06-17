<?php

namespace App\Console\Commands;

use App\Jobs\SendOutreachEmailJob;
use App\Models\OutreachEmailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryFailedOutreachEmailsCommand extends Command
{
    protected $signature = 'garagebook:retry-failed-outreach-emails';

    protected $description = 'Queue failed outreach emails again, once per prospect and campaign.';

    public function handle(): int
    {
        $logs = OutreachEmailLog::query()
            ->with(['campaign', 'prospect'])
            ->where('status', OutreachEmailLog::STATUS_FAILED)
            ->whereNull('sent_at')
            ->whereNull('queued_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (OutreachEmailLog $log): string => $log->outreach_campaign_id . ':' . $log->outreach_prospect_id)
            ->values();

        if ($logs->isEmpty()) {
            $this->info('Geen failed outreach-mails om opnieuw te queue-en.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            if (! $log->campaign || ! $log->prospect || blank($log->to_email)) {
                $skipped++;
                $this->line('Overgeslagen log ' . $log->id . ': ontbrekende campaign/prospect/e-mail.');

                continue;
            }

            try {
                $log->forceFill([
                    'queued_at' => now(),
                ])->save();

                SendOutreachEmailJob::dispatch(
                    (int) $log->outreach_prospect_id,
                    (int) $log->outreach_campaign_id,
                    (string) $log->to_email,
                    (string) $log->subject,
                    (string) $log->body_snapshot,
                );

                $queued++;
                $this->line("Gequeue'd log " . $log->id . ' voor prospect ' . $log->outreach_prospect_id . '.');
            } catch (Throwable $exception) {
                $log->forceFill([
                    'queued_at' => null,
                ])->save();

                $skipped++;

                Log::warning('outreach_retry_queue_failed', [
                    'outreach_email_log_id' => $log->id,
                    'campaign_id' => $log->outreach_campaign_id,
                    'prospect_id' => $log->outreach_prospect_id,
                    'message' => $exception->getMessage(),
                ]);

                $this->error('Kon log ' . $log->id . ' niet opnieuw queue-en: ' . $exception->getMessage());
            }
        }

        $this->info("Opnieuw gequeue'd: " . $queued);
        $this->line('Overgeslagen: ' . $skipped);

        return self::SUCCESS;
    }
}
