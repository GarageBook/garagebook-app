<?php

namespace App\Filament\Resources\FuelLogs\Pages;

use App\Filament\Resources\FuelLogs\FuelLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelLog extends EditRecord
{
    protected static string $resource = FuelLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Verbruiksregel verwijderen')
                ->modalHeading('Verbruiksregel verwijderen')
                ->modalDescription('Dit verwijdert deze tankbeurt uit je verbruiksoverzicht.'),
        ];
    }
}
