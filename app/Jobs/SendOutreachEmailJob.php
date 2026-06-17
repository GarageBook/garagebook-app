<?php

namespace App\Jobs;

use App\Mail\OutreachCampaignMail;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOutreachEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(
        public int $prospectId,
        public int $campaignId,
        public string $toEmail,
        public string $subjectLine,
        public string $bodySnapshot,
    ) {}

    public function middleware(): array
    {
        return [
            (new RateLimited('outreach-email'))->releaseAfter(1),
        ];
    }

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function uniqueId(): string
    {
        return $this->campaignId . ':' . $this->prospectId;
    }

    private function isRateLimitException(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            $message = strtolower($current->getMessage());

            if (str_contains($message, 'too many requests') || str_contains($message, '429') || str_contains($message, 'rate limit')) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function rateLimitRetryDelay(): int
    {
        return match ($this->attempts()) {
            1 => 5,
            2 => 15,
            3 => 30,
            4 => 60,
            5 => 120,
            default => 300,
        };
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
            if ($this->isRateLimitException($exception)) {
                $delay = $this->rateLimitRetryDelay();

                Log::warning('outreach_mail_rate_limited', [
                    'campaign_id' => $this->campaignId,
                    'prospect_id' => $this->prospectId,
                    'to_email' => $this->toEmail,
                    'attempt' => $this->attempts(),
                    'delay_seconds' => $delay,
                    'message' => $exception->getMessage(),
                ]);

                if ($this->attempts() >= $this->tries) {
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

                $this->release($delay);

                return;
            }

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
