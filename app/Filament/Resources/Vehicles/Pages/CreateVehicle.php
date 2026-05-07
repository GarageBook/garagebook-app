<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Services\DistanceUnitService;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected static ?string $title = 'Voertuig toevoegen';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['distance_unit'] = app(DistanceUnitService::class)->normalizeUnit($data['distance_unit'] ?? null);
        $data['current_km'] = (int) round(
            app(DistanceUnitService::class)->toKilometers($data['current_km'] ?? null, $data['distance_unit'], 0) ?? 0
        );

        return $data;
    }
}
