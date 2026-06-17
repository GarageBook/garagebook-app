<?php

namespace App\Filament\Resources\OutreachProspects\Pages;

use App\Filament\Resources\OutreachProspects\OutreachProspectResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOutreachProspect extends ViewRecord
{
    protected static string $resource = OutreachProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyDemoLink')
                ->label('Kopieer demo-link')
                ->action(fn () => null)
                ->extraAttributes([
                    'onclick' => OutreachProspectResource::copyDemoLinkJs($this->getRecord()->demoUrl()),
                ]),
            Action::make('openDemo')
                ->label('Open demo')
                ->url($this->getRecord()->demoUrl())
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
