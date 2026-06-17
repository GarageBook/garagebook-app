<?php

namespace App\Filament\Resources\OutreachProspects\Pages;

use App\Filament\Resources\OutreachProspects\OutreachProspectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOutreachProspect extends EditRecord
{
    protected static string $resource = OutreachProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
