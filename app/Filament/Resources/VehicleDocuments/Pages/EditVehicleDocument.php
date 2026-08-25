<?php

namespace App\Filament\Resources\VehicleDocuments\Pages;

use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Support\VehicleDocumentMetadata;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EditVehicleDocument extends EditRecord
{
    protected static string $resource = VehicleDocumentResource::class;

    protected string|\Filament\Support\Enums\Width|null $maxContentWidth = 'full';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            return VehicleDocumentMetadata::hydrate($data);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.file_path' => $exception->getMessage(),
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
