<?php

namespace App\Filament\Resources\LifecycleEmailTemplates\Pages;

use App\Filament\Resources\LifecycleEmailTemplates\LifecycleEmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListLifecycleEmailTemplates extends ListRecords
{
    protected static string $resource = LifecycleEmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
