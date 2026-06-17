<?php

namespace App\Services\Outreach;

use App\Jobs\SendOutreachEmailJob;
use App\Mail\OutreachCampaignMail;
use App\Models\OutreachCampaign;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OutreachEmailService
{
    public const TEST_RECIPIENT = 'willemvanveelen@icloud.com';

    public function defaultSubject(): string
    {
        return 'Digitaal onderhoudsboekje voor motoren';
    }

    public function defaultBody(): string
    {
        return implode(PHP_EOL, [
            'Beste {{company_name}},',
            '',
            'Ik heb GarageBook ontwikkeld: een digitaal onderhoudsboekje voor motoren.',
            '',
            'Ik heb een korte demo voor u klaargezet. Registreren is niet nodig:',
            '',
            '{{demo_url}}',
            '',
            "In de demo ziet u hoe onderhoud, foto's, documenten en voertuiggeschiedenis centraal kunnen worden bijgehouden.",
            '',
            'Ik ben benieuwd wat u ervan vindt.',
            '',
            'Met vriendelijke groet,',
            '',
            'Willem van Veelen',
            'GarageBook',
            'https://garagebook.nl',
        ]);
    }

    public function subjectForCampaign(OutreachCampaign $campaign): string
    {
        return filled($campaign->email_subject) ? trim((string) $campaign->email_subject) : $this->defaultSubject();
    }

    public function bodyForCampaign(OutreachCampaign $campaign): string
    {
        return filled($campaign->email_body) ? trim((string) $campaign->email_body) : $this->defaultBody();
    }

    /**
     * @return array{subject:string, body:string}
     */
    public function renderForProspect(OutreachCampaign $campaign, OutreachProspect $prospect, bool $isTest = false): array
    {
        $subject = $this->replacePlaceholders($this->subjectForCampaign($campaign), $prospect);
        $body = $this->replacePlaceholders($this->bodyForCampaign($campaign), $prospect);

        if ($isTest) {
            $subject = '[TEST] ' . $subject;
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    public function makeMailForProspect(OutreachCampaign $campaign, OutreachProspect $prospect, bool $isTest = false): OutreachCampaignMail
    {
        $rendered = $this->renderForProspect($campaign, $prospect, $isTest);

        return new OutreachCampaignMail($rendered['subject'], $rendered['body']);
    }

    public function sendTestMail(OutreachCampaign $campaign, OutreachProspect $prospect): void
    {
        $recipient = self::TEST_RECIPIENT;

        if ($recipient !== self::TEST_RECIPIENT) {
            Log::error('outreach_test_mail_invalid_recipient', [
                'recipient' => $recipient,
                'campaign_id' => $campaign->id,
                'prospect_id' => $prospect->id,
            ]);

            throw new \RuntimeException('Testmail-recipient moet exact willemvanveelen@icloud.com zijn.');
        }

        Mail::to($recipient)->send($this->makeMailForProspect($campaign, $prospect, true));
    }

    /**
     * @param  Collection<int, OutreachProspect>  $prospects
     * @return array{queued:int, skipped:int}
     */
    public function queueBulkSend(Collection $prospects): array
    {
        $queued = 0;
        $skipped = 0;

        foreach ($prospects as $prospect) {
            $campaign = $prospect->campaign;

            if (! $campaign instanceof OutreachCampaign) {
                $this->createSkippedLog($prospect, null, 'missing_campaign');
                $skipped++;
                continue;
            }

            if (blank($prospect->email)) {
                $this->createSkippedLog($prospect, $campaign, 'missing_email');
                $skipped++;
                continue;
            }

            if (OutreachEmailLog::query()
                ->where('outreach_campaign_id', $campaign->id)
                ->where('outreach_prospect_id', $prospect->id)
                ->where(function ($query): void {
                    $query->where('status', OutreachEmailLog::STATUS_SENT)
                        ->orWhereNotNull('queued_at');
                })
                ->exists()) {
                $this->createSkippedLog($prospect, $campaign, 'already_sent');
                $skipped++;
                continue;
            }

            $rendered = $this->renderForProspect($campaign, $prospect);

            SendOutreachEmailJob::dispatch(
                $prospect->id,
                $campaign->id,
                (string) $prospect->email,
                $rendered['subject'],
                $rendered['body'],
            );

            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    private function createSkippedLog(OutreachProspect $prospect, ?OutreachCampaign $campaign, string $reason): void
    {
        $subject = $campaign ? $this->renderForProspect($campaign, $prospect)['subject'] : $this->defaultSubject();
        $body = $campaign ? $this->renderForProspect($campaign, $prospect)['body'] : $this->replacePlaceholders($this->defaultBody(), $prospect);

        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign?->id ?? $prospect->outreach_campaign_id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => $prospect->email,
            'subject' => $subject,
            'body_snapshot' => $body,
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => $reason,
        ]);
    }

    private function replacePlaceholders(string $template, OutreachProspect $prospect): string
    {
        return strtr($template, [
            '{{company_name}}' => (string) $prospect->company_name,
            '{{contact_name}}' => filled($prospect->contact_name) ? (string) $prospect->contact_name : (string) $prospect->company_name,
            '{{demo_url}}' => $prospect->demoUrl(),
        ]);
    }
}
