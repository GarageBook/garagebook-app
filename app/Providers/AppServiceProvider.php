<?php

namespace App\Providers;

use App\Services\LocaleService;
use App\Filament\Auth\Http\Responses\RegistrationResponse as CustomRegistrationResponse;
use App\Listeners\QueueMailerLiteSubscription;
use App\Listeners\TrackSuccessfulLogin;
use Carbon\Carbon;
use Filament\Auth\Events\Registered;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RegistrationResponseContract::class, CustomRegistrationResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $localeService = app(LocaleService::class);

        app()->setLocale($localeService->default());
        Carbon::setLocale($localeService->default());

        Event::listen(Login::class, TrackSuccessfulLogin::class);
        Event::listen(Registered::class, QueueMailerLiteSubscription::class);
    }
}
