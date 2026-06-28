<?php

namespace App\Filament\Resources\GrowthProspects\Pages;

use App\Filament\Resources\GrowthProspects\GrowthProspectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrowthProspects extends ListRecords
{
    protected static string $resource = GrowthProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
