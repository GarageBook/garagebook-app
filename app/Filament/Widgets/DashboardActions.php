<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Timeline;
use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\TripLogs\TripLogResource;
use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Support\Analytics;
use Filament\Widgets\Widget;

class DashboardActions extends Widget
{
    protected string $view = 'filament.widgets.dashboard-actions';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return DashboardOnboardingWidget::resolveProgressForUser($user)['is_complete'];
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $vehicle = $user->vehicles()->latest()->first();

        return [
            'vehicle' => $vehicle,
            'actions' => $vehicle ? $this->buildActions($vehicle) : [],
            'booklet' => $vehicle ? $this->buildBooklet($vehicle) : null,
        ];
    }

    private function buildActions(Vehicle $vehicle): array
    {
        $userState = Analytics::userState(auth()->user());
        $publicGarageUrl = app(PublicGarageService::class)->publicUrl($vehicle);

        return [
            [
                'label' => 'Onderhoud toevoegen',
                'url' => MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->id]),
                'attributes' => Analytics::clickTrackingAttributes('quick_maintenance_log_cta_clicked', [
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
            [
                'label' => 'Herinnering toevoegen',
                'url' => $this->reminderUrl($vehicle),
                'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'add_reminder',
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
            [
                'label' => 'Voeg een rit toe',
                'url' => TripLogResource::getUrl('create', ['vehicle_id' => $vehicle->id]),
                'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'add_trip_log',
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
            [
                'label' => 'Voeg een document toe',
                'url' => VehicleDocumentResource::getUrl('create', ['vehicle_id' => $vehicle->id]),
                'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'upload_document',
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
            [
                'label' => 'Bekijk je voertuigpagina',
                'url' => VehicleResource::getUrl('edit', ['record' => $vehicle]),
                'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'view_vehicle_page',
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
            [
                'label' => 'Bekijk je tijdlijn',
                'url' => Timeline::getUrl(['vehicle_id' => $vehicle->id]),
                'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'view_timeline',
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
            [
                'label' => 'Deel je openbare garage',
                'url' => $publicGarageUrl,
                'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'share_public_garage',
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
            [
                'label' => 'Beheer je voertuigen',
                'url' => VehicleResource::getUrl('index'),
                'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'manage_vehicles',
                    'location' => 'dashboard_actions_widget',
                    'user_state' => $userState,
                ]),
            ],
        ];
    }

    private function buildBooklet(Vehicle $vehicle): array
    {
        $maintenanceCount = $vehicle->maintenanceLogs()->count();
        $documentCount = $vehicle->documents()->count();

        return [
            'summary' => 'Jouw onderhoudsboekje bevat nu 1 voertuig, '.$maintenanceCount.' onderhoudslog'.($maintenanceCount === 1 ? '' : 's').' en '.$documentCount.' document'.($documentCount === 1 ? '' : 'en').'.',
            'download_url' => url('/maintenance/pdf?vehicle_id='.$vehicle->id),
            'download_attributes' => Analytics::clickTrackingAttributes('maintenance_booklet_downloaded', [
                'location' => 'dashboard_actions_booklet',
                'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                'user_state' => Analytics::userState(auth()->user()),
            ]),
            'public_cta' => $vehicle->is_public ? [
                'label' => 'Bekijk publieke voertuigpagina',
                'url' => app(PublicGarageService::class)->publicUrl($vehicle),
                'attributes' => Analytics::clickTrackingAttributes('public_share_created', [
                    'source' => 'dashboard_actions_booklet',
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                ]),
            ] : null,
        ];
    }

    private function reminderUrl(Vehicle $vehicle): string
    {
        $latestLog = $vehicle->maintenanceLogs()->latest('maintenance_date')->latest('id')->first();

        if ($latestLog) {
            return MaintenanceLogResource::getUrl('edit', ['record' => $latestLog]).'?with_reminder=1';
        }

        return MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->id, 'with_reminder' => 1]);
    }
}
