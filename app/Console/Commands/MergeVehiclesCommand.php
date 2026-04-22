<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MergeVehiclesCommand extends Command
{
    protected $signature = 'vehicles:merge
        {--from= : Bronvoertuig ID}
        {--into= : Doelvoertuig ID}
        {--force : Voer de merge echt uit}
        {--delete-source : Verwijder het bronvoertuig na succesvolle merge}';

    protected $description = 'Consolideer twee voertuigen door onderhoud en media van bron naar doel te verplaatsen.';

    public function handle(): int
    {
        $sourceId = (int) $this->option('from');
        $targetId = (int) $this->option('into');

        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            $this->error('Geef twee verschillende voertuig-ID’s mee via --from en --into.');

            return self::FAILURE;
        }

        $source = Vehicle::query()->find($sourceId);
        $target = Vehicle::query()->find($targetId);

        if (! $source || ! $target) {
            $this->error('Bron- of doelvoertuig niet gevonden.');

            return self::FAILURE;
        }

        foreach ($this->preview($source, $target) as $key => $value) {
            $this->line(sprintf('%s: %s', $key, json_encode($value)));
        }

        if (! $this->option('force')) {
            $this->warn('Dry run: geen wijzigingen opgeslagen. Gebruik --force om de merge uit te voeren.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($source, $target): void {
                $this->assertMergeIsSafe($source, $target);

                DB::table('maintenance_logs')
                    ->where('vehicle_id', $source->id)
                    ->update(['vehicle_id' => $target->id]);

                $target->forceFill([
                    'user_id' => $target->user_id ?: $source->user_id,
                    'airtable_record_id' => $target->airtable_record_id ?: $source->airtable_record_id,
                    'airtable_synced_at' => $target->airtable_synced_at ?: $source->airtable_synced_at,
                    'brand' => $target->brand ?: $source->brand,
                    'model' => $target->model ?: $source->model,
                    'nickname' => $target->nickname ?: $source->nickname,
                    'license_plate' => $target->license_plate ?: $source->license_plate,
                    'current_km' => max((int) $target->current_km, (int) $source->current_km),
                    'year' => $target->year ?: $source->year,
                    'notes' => $this->mergeText($target->notes, $source->notes),
                    'photo' => $target->photo ?: $source->photo,
                    'photos' => $this->mergePathArrays($target->photos, $source->photos),
                    'media_attachments' => $this->mergePathArrays($target->media_attachments, $source->media_attachments),
                ])->save();

                if ($this->option('delete-source')) {
                    $source->delete();
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Voertuig merge voltooid.');

        return self::SUCCESS;
    }

    private function preview(Vehicle $source, Vehicle $target): array
    {
        return [
            'source_vehicle_id' => $source->id,
            'target_vehicle_id' => $target->id,
            'source_airtable_record_id' => $source->airtable_record_id,
            'target_airtable_record_id' => $target->airtable_record_id,
            'maintenance_to_move' => $source->maintenanceLogs()->count(),
            'source_photo' => $source->photo,
            'target_photo' => $target->photo,
            'will_delete_source' => (bool) $this->option('delete-source'),
        ];
    }

    private function assertMergeIsSafe(Vehicle $source, Vehicle $target): void
    {
        if ($source->user_id !== $target->user_id) {
            throw new RuntimeException('Voertuigen horen niet bij dezelfde user. Merge afgebroken.');
        }

        if ($target->airtable_record_id && $source->airtable_record_id && $target->airtable_record_id !== $source->airtable_record_id) {
            throw new RuntimeException('Doelvoertuig heeft al een andere Airtable-koppeling. Merge afgebroken.');
        }
    }

    private function mergePathArrays(?array $targetPaths, ?array $sourcePaths): array
    {
        return array_values(array_unique(array_filter([
            ...($targetPaths ?? []),
            ...($sourcePaths ?? []),
        ])));
    }

    private function mergeText(?string $target, ?string $source): ?string
    {
        $parts = array_values(array_unique(array_filter([
            $target,
            $source,
        ])));

        return $parts === [] ? null : implode("\n\n", $parts);
    }
}
