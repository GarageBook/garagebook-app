<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Services\DistanceUnitService;
use App\Services\LifecycleEmailService;
use App\Services\PublicGarageService;
use App\Support\AnalyticsEventTracker;
use App\Support\MaintenanceLogVehicleResolver;
use App\Support\UploadedMediaNormalizer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateMaintenanceLog extends CreateRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['vehicle_id'] ?? null)) {
            $data['vehicle_id'] = app(MaintenanceLogVehicleResolver::class)->resolveForUser(
                auth()->user(),
                request()->integer('vehicle_id') ?: null,
            );
        }

        $service = app(DistanceUnitService::class);
        $unit = $service->persistVehicleUnit(
            isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            $data['distance_unit'] ?? null
        );

        $data['km_reading'] = (int) round($service->toKilometers($data['km_reading'] ?? null, $unit, 0) ?? 0);
        $data['interval_km'] = $service->toKilometers($data['interval_km'] ?? null, $unit, 0);
        unset($data['distance_unit']);

        try {
            $data['attachments'] = app(UploadedMediaNormalizer::class)->normalizeMixedAttachmentList($data['attachments'] ?? [], 'public');
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.attachments' => $exception->getMessage(),
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $tracker = app(AnalyticsEventTracker::class);
        $tracker->queueMaintenanceLogCreated($this->record);

        if ($this->record->reminder_enabled) {
            $tracker->queueReminderCreated($this->record->vehicle, 'maintenance');
        }

        $user = auth()->user();
        $maintenanceCount = $user?->vehicles()->withCount('maintenanceLogs')->get()->sum('maintenance_logs_count') ?? 0;

        if ($user && $maintenanceCount === 1) {
            $tracker->queueOnboardingCompleted($user, $this->record);
            app(LifecycleEmailService::class)->markGoalCompletedForFirstMaintenanceLog($user);
        }

        OptimizeMaintenanceLogMedia::dispatch($this->record->getKey());
    }

    protected function getCreatedNotification(): ?Notification
    {
        $vehicle = $this->record->vehicle;

        $notification = Notification::make()
            ->success()
            ->title($vehicle?->is_public
                ? 'Onderhoud toegevoegd. Je publieke voertuigpagina is bijgewerkt.'
                : 'Onderhoud toegevoegd.')
            ->body($vehicle?->is_public
                ? 'Nieuwe onderhoudsregels worden meegenomen op je publieke voertuigpagina. Wanneer is dit onderhoud weer nodig?'
                : 'Je onderhoudshistorie is bijgewerkt. Wanneer is dit onderhoud weer nodig?');

        $actions = [];

        if (! $this->record->reminder_enabled) {
            $actions[] = Action::make('setReminder')
                ->label('Herinnering instellen')
                ->url(MaintenanceLogResource::getUrl('edit', ['record' => $this->record]).'?with_reminder=1')
                ->button();

            $actions[] = Action::make('dismissReminder')
                ->label('Niet nu')
                ->color('gray')
                ->close();
        }

        if ($vehicle?->is_public) {
            $actions[] = Action::make('viewPublicVehiclePage')
                ->label('Bekijk publieke pagina')
                ->url(app(PublicGarageService::class)->publicUrl($vehicle))
                ->openUrlInNewTab();
        }

        if ($actions !== []) {
            $notification->actions($actions);
        }

        return $notification;
    }

    protected function getRedirectUrl(): string
    {
        return MaintenanceLogResource::getUrl('index');
    }

    public function getHeading(): string
    {
        if (request()->query('onboarding') === '1') {
            return 'Voeg je eerste onderhoud toe';
        }

        if (request()->boolean('with_reminder')) {
            return 'Onderhoud met herinnering toevoegen';
        }

        return 'Onderhoud toevoegen';
    }

    public function getSubheading(): ?string
    {
        if (request()->query('onboarding') === '1') {
            return 'Begin met je laatste onderhoud. Je hoeft niet je volledige historie in een keer in te voeren; oudere gegevens kun je later altijd aanvullen.';
        }

        if (request()->boolean('with_reminder')) {
            return 'Leg een onderhoudsmoment vast en zet direct een simpele herinnering klaar voor later.';
        }

        return 'Begin simpel. Je kunt later altijd foto\'s, facturen of details toevoegen.';
    }
}
