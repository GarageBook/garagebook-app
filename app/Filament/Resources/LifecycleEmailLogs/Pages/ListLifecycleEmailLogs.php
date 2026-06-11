<?php

namespace App\Filament\Resources\LifecycleEmailLogs\Pages;

use App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource;
use Filament\Resources\Pages\ListRecords;

class ListLifecycleEmailLogs extends ListRecords
{
    protected static string $resource = LifecycleEmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
