<?php

namespace App\Console\Commands;

use App\Models\VehicleAuthorityIndex;
use App\Services\VehicleAuthorityIndexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VehicleAuthoritySyncCommand extends Command
{
    protected $signature = 'garagebook:vehicle-authority:sync
        {--dry-run : Toon wat er zou veranderen zonder te schrijven}';

    protected $description = 'Synchroniseer de vehicle authority index vanuit de voertuigendatabase.';

    public function handle(VehicleAuthorityIndexService $indexService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        $this->line($dryRun ? 'Dry run – geen wijzigingen opgeslagen.' : 'Sync gestart...');
        $this->newLine();

        // All vehicle counts per brand/model (non-demo users)
        $allCounts = DB::table('vehicles')
            ->join('users', 'vehicles.user_id', '=', 'users.id')
            ->where('users.is_outreach_demo', false)
            ->whereNotNull('vehicles.brand')
            ->whereNotNull('vehicles.model')
            ->where('vehicles.brand', '!=', '')
            ->where('vehicles.model', '!=', '')
            ->groupBy('vehicles.brand', 'vehicles.model')
            ->selectRaw('vehicles.brand, vehicles.model, COUNT(*) as vehicle_count, MIN(vehicles.created_at) as first_seen')
            ->get()
            ->keyBy(fn ($row) => $row->brand.'|'.$row->model);

        // Public vehicle counts per brand/model (non-demo, public, with public_slug)
        $publicCounts = DB::table('vehicles')
            ->join('users', 'vehicles.user_id', '=', 'users.id')
            ->where('users.is_outreach_demo', false)
            ->where('vehicles.is_public', true)
            ->whereNotNull('vehicles.public_slug')
            ->where('vehicles.public_slug', '!=', '')
            ->whereNotNull('vehicles.brand')
            ->whereNotNull('vehicles.model')
            ->where('vehicles.brand', '!=', '')
            ->where('vehicles.model', '!=', '')
            ->groupBy('vehicles.brand', 'vehicles.model')
            ->selectRaw('vehicles.brand, vehicles.model, COUNT(*) as public_vehicle_count')
            ->get()
            ->keyBy(fn ($row) => $row->brand.'|'.$row->model);

        $created = $updated = $skipped = 0;

        foreach ($allCounts as $key => $row) {
            $brand = $row->brand;
            $model = $row->model;
            $slug = VehicleAuthorityIndex::makeSlug($brand, $model);
            $vehicleCount = (int) $row->vehicle_count;
            $publicCount = (int) ($publicCounts[$key]->public_vehicle_count ?? 0);
            $isIndexable = $publicCount > 0;
            $firstSeen = $row->first_seen;

            $existing = VehicleAuthorityIndex::where('slug', $slug)->first();

            if ($existing) {
                $changed = $existing->vehicle_count !== $vehicleCount
                    || $existing->public_vehicle_count !== $publicCount
                    || $existing->is_indexable !== $isIndexable;

                if (! $changed) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    $existing->update([
                        'vehicle_count' => $vehicleCount,
                        'public_vehicle_count' => $publicCount,
                        'is_indexable' => $isIndexable,
                        'last_seen_at' => $now,
                    ]);

                    $indexService->flushSlugCache($slug);
                }

                $updated++;
            } else {
                if (! $dryRun) {
                    VehicleAuthorityIndex::create([
                        'brand' => $brand,
                        'model' => $model,
                        'slug' => $slug,
                        'vehicle_count' => $vehicleCount,
                        'public_vehicle_count' => $publicCount,
                        'is_indexable' => $isIndexable,
                        'first_seen_at' => $firstSeen ?? $now,
                        'last_seen_at' => $now,
                    ]);

                    $indexService->flushSlugCache($slug);
                }

                $created++;
            }
        }

        if (! $dryRun) {
            $indexService->flushCache();
        }

        $totalIndexable = $dryRun
            ? VehicleAuthorityIndex::where('is_indexable', true)->count() + $created
            : VehicleAuthorityIndex::where('is_indexable', true)->count();

        $totalHidden = $dryRun
            ? VehicleAuthorityIndex::where('is_indexable', false)->count()
            : VehicleAuthorityIndex::where('is_indexable', false)->count();

        $this->line("Created:   {$created}");
        $this->line("Updated:   {$updated}");
        $this->line("Skipped:   {$skipped}");
        $this->line("Indexable: {$totalIndexable}");
        $this->line("Hidden:    {$totalHidden}");

        return self::SUCCESS;
    }
}
