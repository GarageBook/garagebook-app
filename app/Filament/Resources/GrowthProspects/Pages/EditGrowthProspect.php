<?php

namespace App\Filament\Resources\GrowthProspects\Pages;

use App\Filament\Resources\GrowthProspects\GrowthProspectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrowthProspect extends EditRecord
{
    protected static string $resource = GrowthProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
