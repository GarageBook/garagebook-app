<?php

namespace App\Filament\Resources\VehicleDocuments\Pages;

use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Support\AnalyticsEventTracker;
use App\Support\VehicleDocumentMetadata;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleDocument extends CreateRecord
{
    protected static string $resource = VehicleDocumentResource::class;

    protected string | \Filament\Support\Enums\Width | null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return VehicleDocumentMetadata::hydrate($data);
    }

    protected function afterCreate(): void
    {
        app(AnalyticsEventTracker::class)->queueDocumentUploaded($this->record);
    }
}
