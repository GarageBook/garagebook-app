<?php

namespace App\Filament\Resources\VehicleDocuments\Pages;

use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Support\VehicleDocumentMetadata;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleDocument extends EditRecord
{
    protected static string $resource = VehicleDocumentResource::class;

    protected string | \Filament\Support\Enums\Width | null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return VehicleDocumentMetadata::hydrate($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
