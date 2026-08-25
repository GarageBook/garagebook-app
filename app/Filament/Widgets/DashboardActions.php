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

        $actions = $vehicle ? $this->buildActions($vehicle) : [];

        return [
            'title' => $actions['title'] ?? 'Je GarageBook is actief',
            'description' => $actions['description'] ?? 'Werk verder aan je onderhoudshistorie wanneer er iets verandert.',
            'primaryAction' => $actions['primary'] ?? null,
            'actions' => $actions['secondary'] ?? [],
        ];
    }

    private function buildActions(Vehicle $vehicle): array
    {
        $user = auth()->user();
        $userState = Analytics::userState($user);
        $publicGarageUrl = app(PublicGarageService::class)->publicUrl($vehicle);
        $maintenanceCount = $user instanceof User
            ? (int) $user->vehicles()->withCount('maintenanceLogs')->get()->sum('maintenance_logs_count')
            : 0;
        $hasActiveReminder = $user instanceof User
            && $user->vehicles()->whereHas('maintenanceLogs', function ($query): void {
                $query->where('reminder_enabled', true)
                    ->where(function ($query): void {
                        $query->whereNotNull('interval_months')
                            ->orWhereNotNull('interval_km');
                    });
            })->exists();

        $addMaintenanceAction = [
            'label' => $maintenanceCount === 1 ? 'Nog een onderhoudsbeurt toevoegen' : 'Onderhoud toevoegen',
            'url' => MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->id]),
            'attributes' => Analytics::clickTrackingAttributes('quick_maintenance_log_cta_clicked', [
                'location' => 'dashboard_actions_widget',
                'user_state' => $userState,
            ]),
        ];

        $reminderAction = [
            'label' => 'Herinnering toevoegen',
            'url' => $this->reminderUrl($vehicle),
            'attributes' => Analytics::clickTrackingAttributes('app_cta_clicked', [
                'cta_name' => 'add_reminder',
                'location' => 'dashboard_actions_widget',
                'user_state' => $userState,
            ]),
        ];

        $secondary = [
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

        if ($maintenanceCount === 1) {
            return [
                'title' => 'Je onderhoudshistorie is gestart',
                'description' => 'Voeg nog een eerdere of andere onderhoudsbeurt toe, zodat GarageBook meer wordt dan een losse registratie.',
                'primary' => $addMaintenanceAction,
                'secondary' => [$reminderAction, ...$secondary],
            ];
        }

        if (! $hasActiveReminder) {
            return [
                'title' => 'Maak GarageBook terugkerend bruikbaar',
                'description' => 'Je historie staat erin. Zet nu een eenvoudige herinnering klaar voor toekomstig onderhoud.',
                'primary' => $reminderAction,
                'secondary' => [$addMaintenanceAction, ...$secondary],
            ];
        }

        return [
            'title' => 'Je GarageBook is actief',
            'description' => 'Werk je historie bij wanneer er nieuw onderhoud, documenten of ritten bijkomen.',
            'primary' => $addMaintenanceAction,
            'secondary' => [$reminderAction, ...$secondary],
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
