<?php

namespace App\Filament\Resources\OutreachCampaigns\Pages;

use App\Filament\Resources\OutreachCampaigns\OutreachCampaignResource;
use App\Support\Outreach\OutreachQuota;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListOutreachCampaigns extends ListRecords
{
    protected static string $resource = OutreachCampaignResource::class;

    public function getHeader(): ?View
    {
        return view('filament.resources.outreach-campaigns.pages.header', [
            'actions' => $this->getCachedHeaderActions(),
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'quotaBanner' => app(OutreachQuota::class)->banner(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
