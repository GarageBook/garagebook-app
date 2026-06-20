<?php

namespace App\Support\Outreach;

use App\Models\OutreachEmailLog;

class OutreachQuota
{
    public function sentToday(): int
    {
        return OutreachEmailLog::query()
            ->where('status', OutreachEmailLog::STATUS_SENT)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    public function dailyLimit(): int
    {
        return max(0, (int) config('services.outreach.daily_limit', 100));
    }

    public function warningThreshold(): int
    {
        return max(0, (int) config('services.outreach.warning_threshold', 95));
    }

    public function hasReachedLimit(): bool
    {
        $limit = $this->dailyLimit();

        return $limit > 0 && $this->sentToday() >= $limit;
    }

    public function shouldWarn(): bool
    {
        return ! $this->hasReachedLimit() && $this->sentToday() >= $this->warningThreshold();
    }

    public function limitReachedMessage(): string
    {
        $sent = $this->sentToday();
        $limit = $this->dailyLimit();

        return "Daglimiet bereikt ({$sent}/{$limit}). Nieuwe outreach-mails worden vandaag niet meer gequeue'd.";
    }

    public function warningMessage(): string
    {
        $sent = $this->sentToday();
        $limit = $this->dailyLimit();

        return "Vandaag zijn {$sent} van de {$limit} toegestane outreach-mails verzonden. Na {$limit} mails stopt GarageBook automatisch met queueën tot morgen.";
    }

    /**
     * @return array{level:string, message:string}|null
     */
    public function banner(): ?array
    {
        if ($this->hasReachedLimit()) {
            return [
                'level' => 'danger',
                'message' => $this->limitReachedMessage(),
            ];
        }

        if ($this->shouldWarn()) {
            return [
                'level' => 'warning',
                'message' => $this->warningMessage(),
            ];
        }

        return null;
    }
}
