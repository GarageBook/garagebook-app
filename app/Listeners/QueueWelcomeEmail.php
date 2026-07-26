<?php

namespace App\Listeners;

use App\Mail\WelcomeToGarageBookMail;
use App\Models\User;
use Filament\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

class QueueWelcomeEmail
{
    public function handle(Registered $event): void
    {
        $user = $event->getUser();

        if (! $user instanceof User) {
            return;
        }

        Mail::to($user->email)->queue(new WelcomeToGarageBookMail($user));
    }
}
