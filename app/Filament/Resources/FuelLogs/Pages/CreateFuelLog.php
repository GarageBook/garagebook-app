<?php

namespace App\Filament\Resources\FuelLogs\Pages;

use App\Filament\Resources\FuelLogs\FuelLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFuelLog extends CreateRecord
{
    protected static string $resource = FuelLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['vehicle_id'] ?? null) && request()->filled('vehicle_id')) {
            $data['vehicle_id'] = (int) request()->integer('vehicle_id');
        }

        return $data;
    }
}
