<?php

namespace App\Services\Lifecycle\Mail;

use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;

class LifecycleMailAdapter
{
    /**
     * NoVehicleRule maps to day 2 because it is the earliest existing no-vehicle nudge.
     * The later no_vehicle_added template remains owned by the v1 lifecycle cadence.
     */
    private const TEMPLATE_MAP = [
        'no_vehicle' => LifecycleEmailTemplate::NO_VEHICLE_DAY2,
        'first_maintenance' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
        'upload_document' => LifecycleEmailTemplate::UPLOAD_DOCUMENT,
        'vehicle_photo_reminder' => LifecycleEmailTemplate::VEHICLE_PHOTO_REMINDER,
        'inactive_maintenance' => LifecycleEmailTemplate::INACTIVE_USER_RETURN,
    ];

    /**
     * @return array{
     *     user_id: int|string|null,
     *     rule_name: string,
     *     template_key: ?string,
     *     subject: ?string,
     *     cta: ?string,
     *     eligible: bool,
     *     reason: string,
     *     blocked_by_mail_cap: bool,
     *     blocked_by_unsubscribe: bool
     * }
     */
    public function preview(User $user, LifecycleRuleResult $result): array
    {
        $templateKey = self::TEMPLATE_MAP[$result->ruleName] ?? null;
        $template = $templateKey ? $this->activeTemplate($templateKey) : null;
        $blockedByUnsubscribe = $user->hasUnsubscribedFromLifecycleEmails();
        $blockedByMailCap = $this->hasRecentLifecycleEmail($user);

        $eligible = $result->matched
            && $template !== null
            && ! $blockedByUnsubscribe
            && ! $blockedByMailCap;

        return [
            'user_id' => $user->getKey(),
            'rule_name' => $result->ruleName,
            'template_key' => $templateKey,
            'subject' => $template?->subject,
            'cta' => $template?->cta_text,
            'eligible' => $eligible,
            'reason' => $this->reason($result, $templateKey, $template, $blockedByUnsubscribe, $blockedByMailCap),
            'blocked_by_mail_cap' => $blockedByMailCap,
            'blocked_by_unsubscribe' => $blockedByUnsubscribe,
        ];
    }

    private function activeTemplate(string $templateKey): ?LifecycleEmailTemplate
    {
        return LifecycleEmailTemplate::query()
            ->where('email_key', $templateKey)
            ->where('is_active', true)
            ->first();
    }

    private function hasRecentLifecycleEmail(User $user): bool
    {
        return LifecycleEmailLog::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [
                LifecycleEmailLog::STATUS_QUEUED,
                LifecycleEmailLog::STATUS_PROCESSING,
                LifecycleEmailLog::STATUS_SENT,
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
    }

    private function reason(
        LifecycleRuleResult $result,
        ?string $templateKey,
        ?LifecycleEmailTemplate $template,
        bool $blockedByUnsubscribe,
        bool $blockedByMailCap,
    ): string {
        if (! $result->matched) {
            return $result->reason;
        }

        if ($templateKey === null) {
            return 'Geen lifecycle mailtemplate mapping voor deze rule.';
        }

        if ($blockedByUnsubscribe) {
            return 'Gebruiker is uitgeschreven voor lifecycle-mails.';
        }

        if ($blockedByMailCap) {
            return 'Lifecycle mail cap actief: er is in de laatste 7 dagen al een queued, processing of sent lifecycle-mail.';
        }

        if ($template === null) {
            return 'Template ontbreekt of is inactief; preview-only en niet eligible.';
        }

        return 'Preview eligible; er wordt niets verstuurd of gequeued.';
    }
}
