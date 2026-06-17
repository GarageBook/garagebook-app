<?php

namespace App\Jobs;

use App\Mail\OutreachCampaignMail;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOutreachEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $prospectId,
        public int $campaignId,
        public string $toEmail,
        public string $subjectLine,
        public string $bodySnapshot,
    ) {}

    public function uniqueId(): string
    {
        return $this->campaignId . ':' . $this->prospectId;
    }

    public function handle(): void
    {
        $prospect = OutreachProspect::query()
            ->with('campaign')
            ->whereKey($this->prospectId)
            ->first();

        if (! $prospect || (int) $prospect->outreach_campaign_id !== $this->campaignId) {
            return;
        }

        if (OutreachEmailLog::query()
            ->where('outreach_campaign_id', $this->campaignId)
            ->where('outreach_prospect_id', $this->prospectId)
            ->where('status', OutreachEmailLog::STATUS_SENT)
            ->exists()) {
            OutreachEmailLog::query()->create([
                'outreach_campaign_id' => $this->campaignId,
                'outreach_prospect_id' => $this->prospectId,
                'to_email' => $this->toEmail,
                'subject' => $this->subjectLine,
                'body_snapshot' => $this->bodySnapshot,
                'status' => OutreachEmailLog::STATUS_SKIPPED,
                'error' => 'already_sent',
            ]);

            return;
        }

        try {
            Mail::to($this->toEmail)->send(new OutreachCampaignMail($this->subjectLine, $this->bodySnapshot));

            OutreachEmailLog::query()->create([
                'outreach_campaign_id' => $this->campaignId,
                'outreach_prospect_id' => $this->prospectId,
                'to_email' => $this->toEmail,
                'subject' => $this->subjectLine,
                'body_snapshot' => $this->bodySnapshot,
                'status' => OutreachEmailLog::STATUS_SENT,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            OutreachEmailLog::query()->create([
                'outreach_campaign_id' => $this->campaignId,
                'outreach_prospect_id' => $this->prospectId,
                'to_email' => $this->toEmail,
                'subject' => $this->subjectLine,
                'body_snapshot' => $this->bodySnapshot,
                'status' => OutreachEmailLog::STATUS_FAILED,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
