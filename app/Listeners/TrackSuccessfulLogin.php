<?php

namespace App\Listeners;

use App\Support\AnalyticsEventTracker;
use Illuminate\Auth\Events\Login;

class TrackSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! method_exists($user, 'forceFill')) {
            return;
        }

        $now = now();

        $user->forceFill([
            'first_login_at' => $user->first_login_at ?: $now,
            'last_login_at' => $now,
        ])->save();

        app(AnalyticsEventTracker::class)->queueLogin(method: 'email');
    }
}
