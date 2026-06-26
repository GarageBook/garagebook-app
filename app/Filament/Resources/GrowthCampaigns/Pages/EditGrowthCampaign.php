<?php

namespace App\Filament\Resources\GrowthCampaigns\Pages;

use App\Filament\Resources\GrowthCampaigns\GrowthCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrowthCampaign extends EditRecord
{
    protected static string $resource = GrowthCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
