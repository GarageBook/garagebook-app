<?php

namespace App\Providers;

use App\Events\Lifecycle\DocumentUploaded;
use App\Events\Lifecycle\FuelLogCreated;
use App\Events\Lifecycle\GaragePublished;
use App\Events\Lifecycle\MaintenanceCreated;
use App\Events\Lifecycle\VehicleCreated;
use App\Filament\Auth\Http\Responses\RegistrationResponse as CustomRegistrationResponse;
use App\Listeners\Lifecycle\UpdateLifecycleProgress;
use App\Listeners\QueueMailerLiteSubscription;
use App\Listeners\TrackSuccessfulLogin;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\TripLog;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Observers\FuelLogObserver;
use App\Observers\MaintenanceLogObserver;
use App\Observers\VehicleDocumentObserver;
use App\Observers\VehicleObserver;
use App\Policies\MaintenanceLogPolicy;
use App\Policies\TripLogPolicy;
use App\Policies\VehicleDocumentPolicy;
use App\Policies\VehiclePolicy;
use App\Services\Growth\Campaigns\CampaignRegistry;
use App\Services\Growth\Campaigns\Community2026Definition;
use App\Services\Growth\Campaigns\Partner2026Definition;
use App\Services\LocaleService;
use Carbon\Carbon;
use Filament\Auth\Events\Registered;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RegistrationResponseContract::class, CustomRegistrationResponse::class);

        $this->app->singleton(CampaignRegistry::class, function (): CampaignRegistry {
            return new CampaignRegistry([
                app(Community2026Definition::class),
                app(Partner2026Definition::class),
            ]);
        });
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

        RateLimiter::for('outreach-email', fn () => Limit::perSecond(4)->by('resend-outreach-email'));
        RateLimiter::for('lifecycle-email', fn () => Limit::perSecond(1)->by('resend-lifecycle-email'));

        Vehicle::observe(VehicleObserver::class);
        MaintenanceLog::observe(MaintenanceLogObserver::class);
        VehicleDocument::observe(VehicleDocumentObserver::class);
        FuelLog::observe(FuelLogObserver::class);

        Event::listen(Login::class, TrackSuccessfulLogin::class);
        Event::listen(Registered::class, QueueMailerLiteSubscription::class);
        Event::listen(VehicleCreated::class, UpdateLifecycleProgress::class);
        Event::listen(MaintenanceCreated::class, UpdateLifecycleProgress::class);
        Event::listen(DocumentUploaded::class, UpdateLifecycleProgress::class);
        Event::listen(FuelLogCreated::class, UpdateLifecycleProgress::class);
        Event::listen(GaragePublished::class, UpdateLifecycleProgress::class);
    }
}
