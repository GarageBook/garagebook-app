<?php

namespace App\Filament\Auth;

use App\Filament\Auth\Http\Responses\RegistrationResponse;
use App\Support\AnalyticsAttribution;
use App\Support\AnalyticsEventTracker;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Events\Registered;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;

class Register extends BaseRegister
{
    public function register(): ?RegistrationResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if ($this->isRegisterRateLimited($this->data['email'] ?? '')) {
            return null;
        }

        $user = $this->wrapInDatabaseTransaction(function (): Model {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeRegister($data);

            $this->callHook('beforeRegister');

            $user = $this->handleRegistration($data);

            $this->form->model($user)->saveRelationships();

            $this->callHook('afterRegister');

            return $user;
        });

        event(new Registered($user));

        $this->sendEmailVerificationNotification($user);

        Filament::auth()->login($user, true);
        session()->regenerate();
        RateLimiter::clear('filament-register:' . sha1((string) $user->email));

        $attribution = app(AnalyticsAttribution::class)->pullForUser($user);

        app(AnalyticsEventTracker::class)->queueSignUp(
            user: $user,
            method: 'email',
            attribution: $attribution,
        );

        return app(RegistrationResponse::class);
    }
}
