<?php

namespace App\Console\Commands;

use App\Jobs\SendOutreachEmailJob;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use App\Services\Outreach\OutreachEmailService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryOverlookedOutreachEmailsCommand extends Command
{
    protected $signature = 'garagebook:retry-overlooked-outreach-emails
        {--campaign= : Filter op campaign-id of slug}
        {--prospect=* : Alleen deze prospect-id(s); herhaal de optie voor meerdere records}';

    protected $description = 'Queue outreach-mails opnieuw voor prospects zonder succesvolle sent-log.';

    public function handle(OutreachEmailService $service): int
    {
        $query = OutreachProspect::query()
            ->with('campaign')
            ->whereDoesntHave('emailLogs', fn (Builder $logQuery) => $logQuery->where('status', OutreachEmailLog::STATUS_SENT));

        $campaignFilter = trim((string) $this->option('campaign'));

        if ($campaignFilter !== '') {
            if (ctype_digit($campaignFilter)) {
                $query->where('outreach_campaign_id', (int) $campaignFilter);
            } else {
                $query->whereHas('campaign', fn (Builder $campaignQuery) => $campaignQuery->where('slug', $campaignFilter));
            }
        }

        $prospectIds = $this->parseProspectIds();

        if ($prospectIds !== []) {
            $query->whereIn('id', $prospectIds);
        }

        $prospects = $query->orderBy('id')->get();

        if ($prospects->isEmpty()) {
            $this->info('Geen outreach-prospects gevonden zonder sent-log.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;
        $skips = [];

        foreach ($prospects as $prospect) {
            $campaign = $prospect->campaign;

            if (! $campaign) {
                $service->recordSkippedMail($prospect, null, 'missing_campaign');
                $skipped++;
                $skips[] = $this->skipContext($prospect, 'missing_campaign');

                continue;
            }

            $email = trim((string) $prospect->email);

            if ($email === '') {
                $service->recordSkippedMail($prospect, $campaign, 'missing_email');
                $skipped++;
                $skips[] = $this->skipContext($prospect, 'missing_email');

                continue;
            }

            if (! $service->isValidEmailAddress($email)) {
                $service->recordSkippedMail($prospect, $campaign, 'invalid_email');
                $skipped++;
                $skips[] = $this->skipContext($prospect, 'invalid_email');

                continue;
            }

            if ($service->hasSentMail($prospect)) {
                $skipped++;
                $skips[] = $this->skipContext($prospect, 'already_sent');

                continue;
            }

            try {
                $service->markRetryQueued($prospect);

                $rendered = $service->renderForProspect($campaign, $prospect);

                SendOutreachEmailJob::dispatch(
                    $prospect->id,
                    $campaign->id,
                    $email,
                    $rendered['subject'],
                    $rendered['body'],
                );

                $queued++;
                $this->line("Gequeue'd prospect {$prospect->id} ({$prospect->company_name}).");
            } catch (Throwable $exception) {
                $skipped++;
                $skips[] = $this->skipContext($prospect, 'queue_failed');

                Log::warning('outreach_retry_overlooked_queue_failed', [
                    'outreach_prospect_id' => $prospect->id,
                    'campaign_id' => $campaign->id,
                    'message' => $exception->getMessage(),
                ]);

                $this->error('Kon prospect ' . $prospect->id . ' niet queue-en: ' . $exception->getMessage());
            }
        }

        $this->newLine();
        $this->info('Gevonden prospects zonder sent-log: ' . $prospects->count());
        $this->info("Gequeue'd: {$queued}");
        $this->info('Overgeslagen: ' . $skipped);

        if ($skips !== []) {
            $this->newLine();
            $this->line('Overslagredenen:');

            foreach ($skips as $skip) {
                $this->line(
                    'Prospect ' . $skip['prospect_id'] . ' (' . $skip['company_name'] . '): ' . $this->describeReason($skip['reason'])
                );
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function parseProspectIds(): array
    {
        $rawValues = (array) $this->option('prospect');
        $ids = [];

        foreach ($rawValues as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            foreach (preg_split('/\s*,\s*/', $value) ?: [] as $chunk) {
                if ($chunk === '') {
                    continue;
                }

                if (! ctype_digit($chunk)) {
                    continue;
                }

                $ids[] = (int) $chunk;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{prospect_id:int, company_name:string, reason:string}
     */
    private function skipContext(OutreachProspect $prospect, string $reason): array
    {
        return [
            'prospect_id' => $prospect->id,
            'company_name' => (string) $prospect->company_name,
            'reason' => $reason,
        ];
    }

    private function describeReason(string $reason): string
    {
        return match ($reason) {
            'missing_campaign' => 'ontbrekende campaign',
            'missing_email' => 'leeg e-mailadres',
            'invalid_email' => 'ongeldig e-mailadres',
            'already_sent' => 'al succesvol verstuurd',
            'queue_failed' => 'queueen mislukt',
            default => $reason,
        };
    }
}
