<?php

namespace App\Filament\Resources\TripLogs\Pages;

use App\Filament\Resources\TripLogs\TripLogResource;
use App\Models\TripLog;
use App\Services\Trips\TripLogProcessingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTripLog extends ViewRecord
{
    protected static string $resource = TripLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reprocess')
                ->label(__('trips.actions.reprocess'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (TripLog $record): bool => $record->canBeReprocessed())
                ->action(function (TripLog $record, TripLogProcessingService $processingService): void {
                    $processingService->reprocess($record);
                }),
            EditAction::make(),
        ];
    }
}
