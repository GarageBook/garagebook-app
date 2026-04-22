<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceLog extends EditRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Onderhoud verwijderen')
                ->modalHeading('Onderhoud verwijderen')
                ->modalDescription('Dit verwijdert het volledige onderhoudsitem. Media kun je hieronder per bestand verwijderen.'),
        ];
    }
}
