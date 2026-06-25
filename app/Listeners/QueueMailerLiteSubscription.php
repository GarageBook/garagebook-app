<?php

namespace App\Listeners;

use App\Jobs\SubscribeUserToMailerLite;
use App\Models\User;
use App\Support\AnalyticsAttribution;
use Filament\Auth\Events\Registered;

class QueueMailerLiteSubscription
{
    public function handle(Registered $event): void
    {
        if (blank(config('services.mailerlite.token')) || blank(config('services.mailerlite.group_id'))) {
            return;
        }

        $user = $event->getUser();

        if (! $user instanceof User) {
            return;
        }

        $groups = [config('services.mailerlite.group_id')];
        $fields = [];
        $attribution = app(AnalyticsAttribution::class)->current();

        if ($user->registration_source === 'geratel') {
            $fields['registration_source'] = 'geratel';
        }

        if (is_array($attribution)) {
            $fields['source'] = $attribution['source'] ?? null;
            $fields['campaign'] = $attribution['campaign_slug'] ?? $attribution['utm_campaign'] ?? null;
            $fields['partner_slug'] = $attribution['partner_slug'] ?? null;
        }

        $groups = array_values(array_filter(
            array_map(fn (mixed $groupId): ?string => filled($groupId) ? (string) $groupId : null, $groups),
        ));
        $fields = array_filter($fields, fn (mixed $value): bool => filled($value));

        SubscribeUserToMailerLite::dispatch($user->email, $user->name, $groups, $fields);
    }
}
