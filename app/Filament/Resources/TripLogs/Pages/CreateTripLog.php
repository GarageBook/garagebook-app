<?php

namespace App\Filament\Resources\TripLogs\Pages;

use App\Filament\Resources\TripLogs\TripLogResource;
use App\Jobs\ProcessTripLogUpload;
use App\Models\Vehicle;
use App\Support\AnalyticsEventTracker;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateTripLog extends CreateRecord
{
    protected static string $resource = TripLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $vehicleId = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;

        if (! $vehicleId || ! Vehicle::query()->whereKey($vehicleId)->where('user_id', auth()->id())->exists()) {
            throw ValidationException::withMessages([
                'data.vehicle_id' => __('trips.validation.invalid_vehicle'),
            ]);
        }

        $data['user_id'] = auth()->id();
        $data['status'] = 'pending';
        $data['source_format'] = $data['source_format'] ?? 'gpx';

        return $data;
    }

    protected function afterCreate(): void
    {
        app(AnalyticsEventTracker::class)->queueTripLogCreated($this->record);
        ProcessTripLogUpload::dispatch($this->record->getKey());
    }
}
