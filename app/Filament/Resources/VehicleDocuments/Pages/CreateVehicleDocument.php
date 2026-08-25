<?php

namespace App\Filament\Resources\VehicleDocuments\Pages;

use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Support\AnalyticsEventTracker;
use App\Support\VehicleDocumentMetadata;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateVehicleDocument extends CreateRecord
{
    protected static string $resource = VehicleDocumentResource::class;

    protected string|\Filament\Support\Enums\Width|null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return VehicleDocumentMetadata::hydrate($data);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.file_path' => $exception->getMessage(),
            ]);
        }
    }

    protected function afterCreate(): void
    {
        app(AnalyticsEventTracker::class)->queueDocumentUploaded($this->record);
    }
}
