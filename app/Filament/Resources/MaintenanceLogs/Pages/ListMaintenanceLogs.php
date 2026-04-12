<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListMaintenanceLogs extends ListRecords
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function getHeaderActions(): array
    {
        $vehicle = Vehicle::where('user_id', auth()->id())
            ->latest()
            ->first();

        $shareUrl = $vehicle
            ? url('/share/' .
                Str::slug(auth()->user()->name) . '/' .
                Str::slug($vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model))
            : url('/');

        return [
            Action::make('openSharePage')
                ->label('Open als externe pagina')
                ->url($shareUrl)
                ->openUrlInNewTab()
                ->color('warning')
                ->outlined(),

            Action::make('copyUrl')
                ->label('Kopieer URL')
                ->extraAttributes([
                    'onclick' => "navigator.clipboard.writeText('{$shareUrl}')",
                ])
                ->color('warning')
                ->outlined(),

            Action::make('exportPdf')
                ->label('Exporteer PDF')
                ->url(url('/maintenance/pdf'))
                ->openUrlInNewTab()
                ->color('warning')
                ->outlined(),
        ];
    }
}