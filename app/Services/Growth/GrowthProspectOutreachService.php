<?php

namespace App\Services\Growth;

use App\Mail\GrowthProspectOutreachMail;
use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GrowthProspectOutreachService
{
    public function __construct(
        private readonly GrowthProspectTrackingUrlGenerator $trackingUrlGenerator,
    ) {}

    /**
     * @param  EloquentCollection<int, GrowthProspect>  $prospects
     * @return array{sent:int, skipped:int}
     */
    public function sendClub2026Bulk(EloquentCollection $prospects): array
    {
        $result = [
            'sent' => 0,
            'skipped' => 0,
        ];

        foreach ($prospects as $prospect) {
            if ($this->sendClub2026($prospect)) {
                $result['sent']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    public function sendClub2026(GrowthProspect $prospect): bool
    {
        $prospect->loadMissing('campaign');

        if ($prospect->status === 'archived') {
            return false;
        }

        $email = trim((string) $prospect->email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $trackingUrl = $this->trackingUrlFor($prospect);

        if ($trackingUrl === null) {
            return false;
        }

        try {
            Mail::to($email)->send(new GrowthProspectOutreachMail(
                $this->recipientNameFor($prospect),
                $trackingUrl,
            ));
        } catch (Throwable $exception) {
            Log::warning('growth_prospect_outreach_send_failed', [
                'growth_prospect_id' => $prospect->id,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $contactedAt = now();

        $prospect->forceFill([
            'status' => 'contacted',
            'last_contacted_at' => $contactedAt,
            'next_follow_up_at' => $contactedAt->copy()->addDays(7),
        ])->save();

        return true;
    }

    private function trackingUrlFor(GrowthProspect $prospect): ?string
    {
        if (! $prospect->campaign) {
            $fallbackCampaign = GrowthCampaign::query()
                ->where('slug', 'club2026')
                ->first();

            if ($fallbackCampaign) {
                $prospect->setRelation('campaign', $fallbackCampaign);
            }
        }

        return $this->trackingUrlGenerator->generate($prospect);
    }

    private function recipientNameFor(GrowthProspect $prospect): string
    {
        return trim((string) ($prospect->contact_name ?: $prospect->name));
    }
}
