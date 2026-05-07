<?php

namespace App\Filament\Widgets;

use App\Services\DistanceUnitService;
use App\Services\VehicleCostService;
use App\Support\MediaPath;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Storage;

class MyVehicles extends Widget
{
    protected string $view = 'filament.widgets.my-vehicles';

    protected int | string | array $columnSpan = 1;

    public function getViewData(): array
    {
        $vehicles = app(VehicleCostService::class)
            ->getVehiclesWithDashboardCostsForUser(auth()->id())
            ->map(function ($vehicle) {
            $paths = [
                $vehicle->photo,
                ...(is_array($vehicle->photos) ? $vehicle->photos : []),
                ...(is_array($vehicle->media_attachments) ? $vehicle->media_attachments : []),
            ];

            $galleryPhotos = collect($paths)
                ->filter(fn (?string $path) => filled($path) && MediaPath::isImage($path))
                ->map(fn (string $path) => Storage::url($path))
                ->unique()
                ->values()
                ->all();

            if ($galleryPhotos === []) {
                $galleryPhotos = [
                    asset('images/garagebook-hero-workshop-motor.webp'),
                ];
            }

            $vehicle->dashboard_gallery_photos = $galleryPhotos;
            $vehicle->current_distance_label = app(DistanceUnitService::class)->formatFromKilometers(
                $vehicle->current_km,
                $vehicle->distance_unit,
                0
            );

            return $vehicle;
        });

        return [
            'vehicles' => $vehicles,
        ];
    }
}
