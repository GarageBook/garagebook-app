<?php

namespace App\Providers;

use App\Services\LocaleService;
use App\Filament\Auth\Http\Responses\RegistrationResponse as CustomRegistrationResponse;
use App\Listeners\QueueMailerLiteSubscription;
use App\Listeners\TrackSuccessfulLogin;
use App\Models\MaintenanceLog;
use App\Models\TripLog;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Policies\MaintenanceLogPolicy;
use App\Policies\TripLogPolicy;
use App\Policies\VehicleDocumentPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;
use Filament\Auth\Events\Registered;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(MaintenanceLog::class, MaintenanceLogPolicy::class);
        Gate::policy(TripLog::class, TripLogPolicy::class);
        Gate::policy(VehicleDocument::class, VehicleDocumentPolicy::class);

        RateLimiter::for('outreach-email', fn () => Limit::perSecond(1)->by('outreach-email'));

        Event::listen(Login::class, TrackSuccessfulLogin::class);
        Event::listen(Registered::class, QueueMailerLiteSubscription::class);
    }
}
