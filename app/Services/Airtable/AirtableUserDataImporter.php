<?php

namespace App\Services\Airtable;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AirtableUserDataImporter
{
    public function __construct(
        private readonly AirtableClient $client,
        private readonly AirtableAssetDownloader $assetDownloader,
    ) {}

    public function importForUser(User $user): array
    {
        $userRecord = $this->client->findUserByRecordId($user->airtable_record_id);
        $vehicleIds = data_get($userRecord, 'fields.Vehicles', []);
        $maintenanceIds = data_get($userRecord, 'fields.Maintenance', []);

        $importedVehicles = [];

        DB::transaction(function () use ($user, $vehicleIds, &$importedVehicles): void {
            foreach ($vehicleIds as $vehicleRecordId) {
                $vehicleRecord = $this->client->getRecord(config('airtable.vehicles_table'), $vehicleRecordId);
                $importedVehicles[$vehicleRecordId] = $this->upsertVehicle($user, $vehicleRecord);
            }
        });

        $importedMaintenance = [];

        DB::transaction(function () use ($maintenanceIds, $importedVehicles, &$importedMaintenance): void {
            foreach ($maintenanceIds as $maintenanceRecordId) {
                $maintenanceRecord = $this->client->getRecord(config('airtable.maintenance_table'), $maintenanceRecordId);
                $vehicleRecordId = data_get($maintenanceRecord, 'fields.Voertuig.0')
                    ?? data_get($maintenanceRecord, 'fields.Vehicle.0');

                if (! $vehicleRecordId || ! isset($importedVehicles[$vehicleRecordId])) {
                    continue;
                }

                $importedMaintenance[] = $this->upsertMaintenance(
                    $importedVehicles[$vehicleRecordId],
                    $maintenanceRecord
                )->airtable_record_id;
            }
        });

        return [
            'vehicles_imported' => count($importedVehicles),
            'maintenance_imported' => count($importedMaintenance),
        ];
    }

    private function upsertVehicle(User $user, array $record): Vehicle
    {
        $fields = $record['fields'] ?? [];
        $vehicleMediaAttachments = $this->downloadVehicleMedia($fields, $record['id']);
        $vehicleImagePaths = $this->assetDownloader->imagePaths($vehicleMediaAttachments);
        $primaryImage = $this->assetDownloader->firstImage($vehicleImagePaths);
        $galleryImages = array_values(array_filter(
            $vehicleImagePaths,
            fn (string $path) => $path !== $primaryImage
        ));
        $genericAttachments = array_values(array_filter(
            $vehicleMediaAttachments,
            fn (string $path) => ! in_array($path, $vehicleImagePaths, true)
        ));

        return Vehicle::query()->updateOrCreate(
            ['airtable_record_id' => $record['id']],
            [
                'user_id' => $user->id,
                'brand' => (string) ($fields['Brand'] ?? 'Onbekend'),
                'model' => (string) ($fields['Model'] ?? 'Onbekend'),
                'nickname' => $fields['Name'] ?? null,
                'license_plate' => $fields['License_plate'] ?? null,
                'current_km' => (int) round((float) ($fields['Current_km'] ?? 0)),
                'year' => isset($fields['Year']) ? (int) $fields['Year'] : null,
                'notes' => $fields['Notes'] ?? null,
                'photo' => $primaryImage,
                'photos' => $galleryImages,
                'media_attachments' => $genericAttachments,
                'airtable_synced_at' => now(),
            ]
        );
    }

    private function upsertMaintenance(Vehicle $vehicle, array $record): MaintenanceLog
    {
        $fields = $record['fields'] ?? [];
        $description = trim((string) ($fields['Title'] ?? $fields['Beschrijving'] ?? 'Onderhoud'));
        $body = trim((string) ($fields['Beschrijving'] ?? ''));
        $type = trim((string) ($fields['Type'] ?? ''));
        $attachments = $this->assetDownloader->downloadMany(
            $fields['Attachments'] ?? [],
            'maintenance-attachments/' . $record['id']
        );
        $notes = trim(implode("\n\n", array_filter([
            $type !== '' ? 'Type: ' . $type : null,
            $body !== '' && $body !== $description ? $body : null,
        ])));

        return MaintenanceLog::query()->updateOrCreate(
            ['airtable_record_id' => $record['id']],
            [
                'vehicle_id' => $vehicle->id,
                'description' => $description,
                'km_reading' => (int) round((float) ($fields['Kilometerstand'] ?? 0)),
                'maintenance_date' => $fields['Datum'] ?? now()->toDateString(),
                'cost' => isset($fields['Kosten']) ? (float) $fields['Kosten'] : null,
                'attachments' => $attachments,
                'notes' => $notes !== '' ? $notes : null,
                'airtable_synced_at' => now(),
            ]
        );
    }

    private function downloadVehicleMedia(array $fields, string $vehicleRecordId): array
    {
        $attachments = $fields['Photo'] ?? [];
        $mediaRecordIds = $fields['Media'] ?? [];

        foreach ($mediaRecordIds as $mediaRecordId) {
            $mediaRecord = $this->client->getRecord(config('airtable.media_table'), $mediaRecordId);
            $mediaFields = $mediaRecord['fields'] ?? [];

            $attachments = array_merge(
                $attachments,
                $mediaFields['main_image'] ?? [],
                $mediaFields['photos'] ?? [],
                $mediaFields['videos'] ?? [],
            );
        }

        return $this->assetDownloader->downloadMany(
            $attachments,
            'vehicle-attachments/' . $vehicleRecordId
        );
    }
}
