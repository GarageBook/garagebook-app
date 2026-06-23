<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Support\Analytics;
use App\Support\AnalyticsEventTracker;
use Filament\Widgets\Widget;

class PublicVehiclePagesWidget extends Widget
{
    protected string $view = 'filament.widgets.public-vehicle-pages-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->vehicles()->exists();
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $vehicles = $user->vehicles()
            ->withCount('maintenanceLogs')
            ->latest()
            ->get();
        $publicVehicles = $vehicles
            ->where('is_public', true)
            ->take(3)
            ->map(fn (Vehicle $vehicle): array => $this->publicVehicleData($vehicle))
            ->values();

        if ($publicVehicles->isNotEmpty()) {
            app(AnalyticsEventTracker::class)->queuePublicVehicleDashboardWidgetViewed($publicVehicles->count());
        }

        return [
            'activationUrl' => $vehicles->first() ? VehicleResource::getUrl('edit', ['record' => $vehicles->first()]) : null,
            'publicVehicles' => $publicVehicles,
        ];
    }

    private function publicVehicleData(Vehicle $vehicle): array
    {
        $vehicleHash = Analytics::anonymizeIdentifier('vehicle', $vehicle->id);

        return [
            'name' => trim(implode(' ', array_filter([
                $vehicle->nickname,
                $vehicle->nickname ? null : $vehicle->brand,
                $vehicle->nickname ? null : $vehicle->model,
            ]))),
            'maintenance_count' => (int) ($vehicle->maintenance_logs_count ?? 0),
            'public_url' => app(PublicGarageService::class)->publicUrl($vehicle),
            'view_attributes' => Analytics::clickTrackingAttributes('public_vehicle_page_view_clicked', [
                'location' => 'dashboard_public_vehicle_pages_widget',
                'vehicle_id_hash' => $vehicleHash,
            ]),
            'copy_attributes' => Analytics::clickTrackingAttributes('public_vehicle_page_link_copied', [
                'location' => 'dashboard_public_vehicle_pages_widget',
                'vehicle_id_hash' => $vehicleHash,
            ]),
        ];
    }
}
