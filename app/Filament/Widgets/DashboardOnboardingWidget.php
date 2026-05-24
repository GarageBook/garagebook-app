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
    protected string $view = 'filament.widgets.dashboard-onboarding-widget';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var User $user */
        $user = auth()->user();
        
        // Hide if user already has maintenance logs
        return $user->vehicles()->withCount('maintenanceLogs')->get()->sum('maintenance_logs_count') === 0;
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $vehicleCount = $user->vehicles()->count();
        $vehicle = $user->vehicles()->first();

        $steps = [];

        if ($vehicleCount === 0) {
            $steps[] = [
                'title' => 'Stap 1: Voeg je motor toe',
                'description' => 'Begin met het vastleggen van je motorfiets om je digitale garage te starten.',
                'cta' => 'Voeg motor toe',
                'url' => VehicleResource::getUrl('create'),
                'icon' => 'heroicon-o-plus-circle',
            ];
        } else {
            $steps[] = [
                'title' => 'Stap 2: Voeg je eerste onderhoud toe',
                'description' => 'Houd je historie actueel. Voeg je laatste beurt of reparatie toe.',
                'cta' => 'Onderhoud toevoegen',
                'url' => MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->id]),
                'icon' => 'heroicon-o-wrench-screwdriver',
                'primary' => true,
            ];

            $steps[] = [
                'title' => 'Stap 3: Upload een document of foto',
                'description' => 'Sla je facturen, kentekenbewijs of foto\'s veilig op in de cloud.',
                'cta' => 'Document uploaden',
                'url' => VehicleDocumentResource::getUrl('create', ['vehicle_id' => $vehicle->id]),
                'icon' => 'heroicon-o-document-arrow-up',
            ];

            $steps[] = [
                'title' => 'Maak je garage compleet',
                'description' => 'Voeg meer details toe aan je voertuig voor een volledig overzicht.',
                'cta' => 'Voertuig aanpassen',
                'url' => VehicleResource::getUrl('edit', ['record' => $vehicle->id]),
                'icon' => 'heroicon-o-pencil-square',
            ];
        }

        return [
            'steps' => $steps,
        ];
    }
}
