<?php

namespace App\Listeners;

use App\Jobs\SubscribeUserToMailerLite;
use App\Models\User;
use Filament\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

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

        if ($user->registration_source === 'geratel') {
            $geratelGroupId = config('services.mailerlite.geratel_group_id');

            if (blank($geratelGroupId)) {
                Log::warning('Geratel registration missing MailerLite Geratel group ID.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

            $groups[] = $geratelGroupId;
        }

        $groups = array_values(array_filter(
            array_map(fn (mixed $groupId): ?string => filled($groupId) ? (string) $groupId : null, $groups),
        ));

        SubscribeUserToMailerLite::dispatch($user->email, $user->name, $groups);
    }
}
