<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class ImportUserSnapshotCommand extends Command
{
    protected $signature = 'users:import-snapshot
        {export : Absoluut pad naar willem-export.json}
        {--media= : Absoluut pad naar willem-media.tar.gz}
        {--email= : Lokaal user-emailadres waarop de import moet landen}
        {--force : Voer de import echt uit}';

    protected $description = 'Importeer een user-specifieke snapshot uit productie naar een bestaand lokaal account.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Dit commando is alleen beschikbaar in lokale of testomgevingen.');

            return self::FAILURE;
        }

        $exportPath = (string) $this->argument('export');
        $mediaPath = $this->option('media');
        $targetEmail = (string) ($this->option('email') ?: 'willemvanveelen@icloud.com');

        if (! is_file($exportPath)) {
            $this->error("Exportbestand niet gevonden: {$exportPath}");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($exportPath), true);

        if (! is_array($payload)) {
            $this->error('Exportbestand bevat geen geldige JSON.');

            return self::FAILURE;
        }

        $targetUser = User::query()->where('email', $targetEmail)->first();

        if (! $targetUser) {
            $this->error("Lokaal account niet gevonden voor {$targetEmail}.");

            return self::FAILURE;
        }

        $preview = [
            'target_user_id' => $targetUser->id,
            'target_email' => $targetUser->email,
            'vehicles' => count($payload['vehicles'] ?? []),
            'maintenance_logs' => count($payload['maintenance_logs'] ?? []),
            'fuel_logs' => count($payload['fuel_logs'] ?? []),
            'vehicle_documents' => count($payload['vehicle_documents'] ?? []),
            'media_archive' => $mediaPath ?: 'geen',
            'existing_local_vehicles' => $targetUser->vehicles()->count(),
        ];

        foreach ($preview as $key => $value) {
            $this->line(sprintf('%s: %s', $key, is_scalar($value) ? (string) $value : json_encode($value)));
        }

        if (! $this->option('force')) {
            $this->warn('Dry run: geen wijzigingen opgeslagen. Gebruik --force om de import echt uit te voeren.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($payload, $targetUser): void {
                $this->importSnapshot($payload, $targetUser);
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($mediaPath) {
            try {
                $this->extractMediaArchive((string) $mediaPath);
            } catch (\Throwable $exception) {
                $this->warn('Data is wel geïmporteerd, maar media uitpakken mislukte: '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('Snapshot succesvol lokaal geïmporteerd.');

        return self::SUCCESS;
    }

    private function importSnapshot(array $payload, User $targetUser): void
    {
        $sourceUser = Arr::get($payload, 'user', []);
        $vehicles = Arr::get($payload, 'vehicles', []);
        $maintenanceLogs = Arr::get($payload, 'maintenance_logs', []);
        $fuelLogs = Arr::get($payload, 'fuel_logs', []);
        $vehicleDocuments = Arr::get($payload, 'vehicle_documents', []);

        $this->assertPayloadShape($vehicles, $maintenanceLogs, $fuelLogs, $vehicleDocuments);

        $targetUser->forceFill([
            'name' => $sourceUser['name'] ?? $targetUser->name,
            'is_admin' => $sourceUser['is_admin'] ?? $targetUser->is_admin,
            'first_login_at' => $sourceUser['first_login_at'] ?? $targetUser->first_login_at,
            'last_login_at' => $sourceUser['last_login_at'] ?? $targetUser->last_login_at,
            'airtable_record_id' => $sourceUser['airtable_record_id'] ?? $targetUser->airtable_record_id,
            'airtable_synced_at' => $sourceUser['airtable_synced_at'] ?? $targetUser->airtable_synced_at,
            'consumption_unit' => $sourceUser['consumption_unit'] ?? $targetUser->consumption_unit,
        ])->save();

        $existingVehicleIds = DB::table('vehicles')
            ->where('user_id', $targetUser->id)
            ->pluck('id');

        if ($existingVehicleIds->isNotEmpty()) {
            DB::table('vehicle_documents')->whereIn('vehicle_id', $existingVehicleIds)->delete();
            DB::table('fuel_logs')->whereIn('vehicle_id', $existingVehicleIds)->delete();
            DB::table('maintenance_logs')->whereIn('vehicle_id', $existingVehicleIds)->delete();
            DB::table('vehicles')->whereIn('id', $existingVehicleIds)->delete();
        }

        $vehicleIdMap = [];

        foreach ($vehicles as $vehicle) {
            $sourceId = (int) $vehicle['id'];
            unset($vehicle['id']);

            $vehicle['user_id'] = $targetUser->id;
            $vehicle = $this->encodeArrayColumns($vehicle, [
                'photos',
                'media_attachments',
            ]);
            $newId = (int) DB::table('vehicles')->insertGetId($vehicle);
            $vehicleIdMap[$sourceId] = $newId;
        }

        foreach ($maintenanceLogs as $log) {
            $sourceVehicleId = (int) $log['vehicle_id'];

            if (! array_key_exists($sourceVehicleId, $vehicleIdMap)) {
                continue;
            }

            unset($log['id']);
            $log['vehicle_id'] = $vehicleIdMap[$sourceVehicleId];
            $log = $this->encodeArrayColumns($log, [
                'attachments',
                'media_attachments',
                'file_attachments',
            ]);
            DB::table('maintenance_logs')->insert($log);
        }

        foreach ($fuelLogs as $fuelLog) {
            $sourceVehicleId = (int) $fuelLog['vehicle_id'];

            if (! array_key_exists($sourceVehicleId, $vehicleIdMap)) {
                continue;
            }

            unset($fuelLog['id']);
            $fuelLog['vehicle_id'] = $vehicleIdMap[$sourceVehicleId];
            DB::table('fuel_logs')->insert($fuelLog);
        }

        foreach ($vehicleDocuments as $document) {
            $sourceVehicleId = (int) $document['vehicle_id'];

            if (! array_key_exists($sourceVehicleId, $vehicleIdMap)) {
                continue;
            }

            unset($document['id']);
            $document['vehicle_id'] = $vehicleIdMap[$sourceVehicleId];
            DB::table('vehicle_documents')->insert($document);
        }
    }

    private function extractMediaArchive(string $mediaPath): void
    {
        if (! is_file($mediaPath)) {
            throw new RuntimeException("Media-archief niet gevonden: {$mediaPath}");
        }

        $destination = storage_path('app/public');

        if (! is_dir($destination)) {
            mkdir($destination, 0775, true);
        }

        $process = new Process(['tar', '-xzf', $mediaPath, '-C', $destination]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function assertPayloadShape(array $vehicles, array $maintenanceLogs, array $fuelLogs, array $vehicleDocuments): void
    {
        foreach ([
            'vehicles' => $vehicles,
            'maintenance_logs' => $maintenanceLogs,
            'fuel_logs' => $fuelLogs,
            'vehicle_documents' => $vehicleDocuments,
        ] as $key => $rows) {
            if (! is_array($rows)) {
                throw new RuntimeException("Payload-sectie {$key} is ongeldig.");
            }
        }
    }

    private function encodeArrayColumns(array $row, array $columns): array
    {
        foreach ($columns as $column) {
            if (! array_key_exists($column, $row)) {
                continue;
            }

            if ($row[$column] === null) {
                continue;
            }

            if (is_array($row[$column])) {
                $row[$column] = json_encode($row[$column], JSON_UNESCAPED_SLASHES);
            }
        }

        return $row;
    }
}
