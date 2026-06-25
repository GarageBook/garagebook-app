<?php

namespace App\Listeners;

use App\Jobs\SubscribeUserToMailerLite;
use App\Models\User;
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

        if ($user->registration_source === 'geratel') {
            $fields['registration_source'] = 'geratel';
        }

        $groups = array_values(array_filter(
            array_map(fn (mixed $groupId): ?string => filled($groupId) ? (string) $groupId : null, $groups),
        ));

        SubscribeUserToMailerLite::dispatch($user->email, $user->name, $groups, $fields);
    }
}
