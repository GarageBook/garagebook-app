<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Concerns\HasPublicVehicleShareActions;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicle extends ViewRecord
{
    use HasPublicVehicleShareActions;

    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getPublicVehicleShareActions($this->getRecord(), includeCopyUrl: false),
            EditAction::make(),
        ];
    }
}
