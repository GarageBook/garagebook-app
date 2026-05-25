<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\Widget;

class DashboardOnboardingWidget extends Widget
{
    private const STATE_NO_VEHICLES = 'no_vehicles';

    private const STATE_NEEDS_MAINTENANCE = 'needs_maintenance';

    private const STATE_NEEDS_DOCUMENTS = 'needs_documents';

    private const STATE_COMPLETE = 'complete';

    protected string $view = 'filament.widgets.dashboard-onboarding-widget';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return static::resolveStateForUser($user) !== self::STATE_COMPLETE;
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $state = self::resolveStateForUser($user);
        $vehicle = $user->vehicles()->orderBy('id')->first();

        return [
            'state' => $state,
            'panel' => $this->buildPanel($state, $vehicle),
        ];
    }

    private static function resolveStateForUser(User $user): string
    {
        if (! $user->vehicles()->exists()) {
            return self::STATE_NO_VEHICLES;
        }

        if (! $user->vehicles()->whereHas('maintenanceLogs')->exists()) {
            return self::STATE_NEEDS_MAINTENANCE;
        }

        if (! $user->vehicles()->whereHas('documents')->exists()) {
            return self::STATE_NEEDS_DOCUMENTS;
        }

        return self::STATE_COMPLETE;
    }

    private function buildPanel(string $state, ?Vehicle $vehicle): ?array
    {
        return match ($state) {
            self::STATE_NO_VEHICLES => [
                'tone' => 'prominent',
                'title' => 'Voeg je eerste voertuig toe',
                'description' => 'Start je digitale garage door eerst je motor toe te voegen.',
                'icon' => 'heroicon-o-plus-circle',
                'primaryCta' => [
                    'label' => 'Voertuig toevoegen',
                    'url' => VehicleResource::getUrl('create'),
                ],
            ],
            self::STATE_NEEDS_MAINTENANCE => [
                'tone' => 'prominent',
                'title' => 'Voeg je eerste onderhoud toe',
                'description' => 'Je voertuig staat klaar. Leg nu je eerste beurt of reparatie vast.',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'primaryCta' => [
                    'label' => 'Onderhoud toevoegen',
                    'url' => MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle?->id]),
                ],
                'secondaryCta' => $vehicle ? [
                    'label' => 'Voertuig aanpassen',
                    'url' => VehicleResource::getUrl('edit', ['record' => $vehicle->id]),
                ] : null,
            ],
            self::STATE_NEEDS_DOCUMENTS => [
                'tone' => 'subtle',
                'title' => 'Maak je garage completer',
                'description' => 'Upload een factuur, foto of document als extra bewijs bij je historie.',
                'icon' => 'heroicon-o-document-arrow-up',
                'primaryCta' => [
                    'label' => 'Document uploaden',
                    'url' => VehicleDocumentResource::getUrl('create', ['vehicle_id' => $vehicle?->id]),
                ],
            ],
            default => null,
        };
    }
}
