<?php

namespace App\Filament\Resources\TripLogs\Pages;

use App\Filament\Resources\TripLogs\TripLogResource;
use App\Models\TripLog;
use App\Models\Vehicle;
use App\Services\Trips\TripLogProcessingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditTripLog extends EditRecord
{
    protected static string $resource = TripLogResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $vehicleId = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;

        if (! $vehicleId || ! Vehicle::query()->whereKey($vehicleId)->where('user_id', auth()->id())->exists()) {
            throw ValidationException::withMessages([
                'data.vehicle_id' => __('trips.validation.invalid_vehicle'),
            ]);
        }

        return $data;
    }

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
            DeleteAction::make(),
        ];
    }
}
