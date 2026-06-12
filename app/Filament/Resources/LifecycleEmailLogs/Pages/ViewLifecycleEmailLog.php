<?php

namespace App\Filament\Resources\LifecycleEmailLogs\Pages;

use App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLifecycleEmailLog extends ViewRecord
{
    protected static string $resource = LifecycleEmailLogResource::class;

    public function mount(int | string $record): void
    {
        abort_unless(LifecycleEmailLogResource::hasBackingTable(), 404);

        parent::mount($record);
    }
}
