<?php

namespace App\Filament\Resources\GrowthProspects\Pages;

use App\Filament\Resources\GrowthProspects\GrowthProspectResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrowthProspects extends ListRecords
{
    protected static string $resource = GrowthProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importProspects')
                ->label('Import prospects')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(static::getResource()::getUrl('import')),
            CreateAction::make()
                ->label('Create Prospect'),
        ];
    }
}
