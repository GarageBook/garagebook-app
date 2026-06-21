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
    public function mount(): void
    {
        parent::mount();

        if (! request()->isMethod('GET') || request()->header('X-Livewire')) {
            return;
        }

        app(AnalyticsEventTracker::class)->queueRegisterStart(
            app(AnalyticsAttribution::class)->current(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data = parent::mutateFormDataBeforeRegister($data);
        $attribution = app(AnalyticsAttribution::class)->current();

        if (($attribution['source'] ?? null) === 'outreach_demo') {
            $data['registration_source'] = 'outreach_demo';
        }

        return $data;
    }

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
        RateLimiter::clear('filament-register:'.sha1((string) $user->email));

        $attribution = app(AnalyticsAttribution::class)->pullForUser($user);

        app(AnalyticsEventTracker::class)->queueSignUp(
            method: 'email',
            registrationSource: $user->registration_source,
            attribution: $attribution?->only(['source', 'demo_user_id', 'outreach_prospect_id', 'intended']),
        );

        return app(RegistrationResponse::class);
    }
}
