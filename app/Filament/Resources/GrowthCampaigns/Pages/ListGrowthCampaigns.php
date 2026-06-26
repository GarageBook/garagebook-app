<?php

namespace App\Filament\Resources\GrowthCampaigns\Pages;

use App\Filament\Resources\GrowthCampaigns\GrowthCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrowthCampaigns extends ListRecords
{
    protected static string $resource = GrowthCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
