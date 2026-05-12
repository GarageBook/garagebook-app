<?php

namespace App\Filament\Resources\TripLogs\Pages;

use App\Filament\Resources\TripLogs\TripLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTripLogs extends ListRecords
{
    protected static string $resource = TripLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn (): string => static::getResource()::getUrl('create', [
                    'vehicle_id' => request()->integer('vehicle_id') ?: null,
                ])),
        ];
    }
}
