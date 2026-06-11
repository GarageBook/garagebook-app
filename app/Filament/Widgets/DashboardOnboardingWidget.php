<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Analytics;
use App\Support\AnalyticsEventTracker;
use Filament\Widgets\Widget;

class DashboardOnboardingWidget extends Widget
{
    private const STEP_VEHICLE = 'vehicle';

    private const STEP_MAINTENANCE = 'maintenance';

    private const STEP_DOCUMENT = 'document';

    protected string $view = 'filament.widgets.dashboard-onboarding-widget';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return ! static::resolveProgressForUser($user)['is_complete'];
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $progress = static::resolveProgressForUser($user);

        try {
            app(AnalyticsEventTracker::class)->queueOnboardingWidgetViewed(
                $progress['next_step'],
                $progress['completed_steps'],
                $progress['total_steps']
            );
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        return [
            'title' => $this->resolveTitle($progress),
            'description' => $this->resolveDescription($progress),
            'microcopy' => $this->resolveMicrocopy($progress),
            'progress' => $progress,
            'primaryCta' => $this->buildPrimaryCta($progress),
        ];
    }

    public static function resolveProgressForUser(User $user): array
    {
        $vehicle = $user->vehicles()
            ->withCount(['maintenanceLogs', 'documents'])
            ->orderBy('id')
            ->first();

        $hasVehicle = $vehicle instanceof Vehicle;
        $hasMaintenance = $hasVehicle
            && MaintenanceLog::query()
                ->whereHas('vehicle', fn ($query) => $query->where('user_id', $user->getKey()))
                ->exists();
        $hasDocument = $hasVehicle
            && $user->vehicles()->whereHas('documents')->exists();

        $steps = [
            [
                'key' => self::STEP_VEHICLE,
                'label' => 'Voertuig toevoegen',
                'status' => $hasVehicle ? 'completed' : 'open',
                'is_optional' => false,
            ],
            [
                'key' => self::STEP_MAINTENANCE,
                'label' => 'Eerste onderhoudslog toevoegen',
                'status' => $hasMaintenance ? 'completed' : 'open',
                'is_optional' => false,
            ],
            [
                'key' => self::STEP_DOCUMENT,
                'label' => 'Factuur of foto toevoegen',
                'status' => $hasDocument ? 'completed' : 'open',
                'is_optional' => true,
            ],
        ];

        $completedSteps = collect($steps)
            ->where('status', 'completed')
            ->count();
        $isComplete = $hasVehicle && $hasMaintenance;
        $nextStep = collect($steps)
            ->first(fn (array $step): bool => $step['status'] === 'open' && ! $step['is_optional']);
        $nextStepKey = is_array($nextStep)
            ? $nextStep['key']
            : ($hasVehicle ? self::STEP_DOCUMENT : self::STEP_VEHICLE);

        return [
            'vehicle' => $vehicle,
            'steps' => $steps,
            'completed_steps' => $completedSteps,
            'total_steps' => count($steps),
            'completion_percentage' => (int) round(($completedSteps / count($steps)) * 100),
            'next_step' => $nextStepKey,
            'has_vehicle' => $hasVehicle,
            'has_maintenance' => $hasMaintenance,
            'has_document' => $hasDocument,
            'is_complete' => $isComplete,
        ];
    }

    private function resolveTitle(array $progress): string
    {
        if ($progress['has_vehicle'] && ! $progress['has_maintenance']) {
            return 'Mooi, je voertuig staat erin.';
        }

        return 'Maak je GarageBook compleet';
    }

    private function resolveDescription(array $progress): string
    {
        if ($progress['has_vehicle'] && ! $progress['has_maintenance']) {
            return 'Voeg nu je laatste onderhoudsbeurt toe. Zelfs een registratie maakt je onderhoudsgeschiedenis direct waardevoller.';
        }

        return 'Een complete onderhoudshistorie begint met je eerste registratie. Voeg je voertuig en onderhoud toe en bouw direct aan een waardevol dossier.';
    }

    private function resolveMicrocopy(array $progress): ?string
    {
        if ($progress['next_step'] !== self::STEP_MAINTENANCE) {
            return null;
        }

        return 'Bijvoorbeeld een oliebeurt, bandenwissel, kettingonderhoud of reparatie.';
    }

    private function buildPrimaryCta(array $progress): array
    {
        $vehicle = $progress['vehicle'];
        $userState = Analytics::userState(auth()->user());

        return match ($progress['next_step']) {
            self::STEP_MAINTENANCE => [
                'label' => 'Voeg je laatste onderhoud toe',
                'url' => MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle?->id]),
                'attributes' => Analytics::clickTrackingAttributes('onboarding_maintenance_cta_clicked', [
                    'location' => 'dashboard_onboarding_widget',
                    'user_state' => $userState,
                    'completed_steps' => $progress['completed_steps'],
                ]),
            ],
            self::STEP_DOCUMENT => [
                'label' => 'Factuur of foto toevoegen',
                'url' => VehicleDocumentResource::getUrl('create', ['vehicle_id' => $vehicle?->id]),
                'attributes' => Analytics::clickTrackingAttributes('onboarding_document_cta_clicked', [
                    'location' => 'dashboard_onboarding_widget',
                    'user_state' => $userState,
                    'completed_steps' => $progress['completed_steps'],
                ]),
            ],
            default => [
                'label' => 'Voertuig toevoegen',
                'url' => VehicleResource::getUrl('create'),
                'attributes' => Analytics::clickTrackingAttributes('onboarding_vehicle_cta_clicked', [
                    'location' => 'dashboard_onboarding_widget',
                    'user_state' => $userState,
                    'completed_steps' => $progress['completed_steps'],
                ]),
            ],
        };
    }
}
