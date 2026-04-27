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

        SubscribeUserToMailerLite::dispatch($user->email, $user->name);
    }
}
