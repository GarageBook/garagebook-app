<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\PublicGarageService;
use Illuminate\Console\Command;

class PublishAllVehiclesCommand extends Command
{
    protected $signature = 'garagebook:publish-all-vehicles';

    protected $description = 'Generate missing public slugs and report public vehicle indexability.';

    public function handle(PublicGarageService $publicGarageService): int
    {
        $total = Vehicle::query()->count();
        $generatedSlugs = 0;
        $legacyPublished = 0;

        Vehicle::query()
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($vehicles) use ($publicGarageService, &$generatedSlugs, &$legacyPublished): void {
                foreach ($vehicles as $vehicle) {
                    $updates = [];

                    if (blank($vehicle->public_slug)) {
                        $updates['public_slug'] = $publicGarageService->generatePublicSlug($vehicle, $vehicle->id);
                        $generatedSlugs++;
                    }

                    if ($vehicle->getRawOriginal('is_public') === null) {
                        $updates['is_public'] = true;
                        $legacyPublished++;
                    }

                    if ($updates !== []) {
                        $vehicle->forceFill($updates)->saveQuietly();
                    }
                }
            });

        $vehicles = Vehicle::query()
            ->with([
                'user',
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->get();

        $public = $vehicles->where('is_public', true)->count();
        $hidden = $vehicles->where('is_public', false)->count();
        $outreachDemoExcluded = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => $publicGarageService->isOutreachDemoVehicle($vehicle))
            ->count();
        $indexable = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => $publicGarageService->shouldIndex($vehicle))
            ->count();
        $sitemapCount = $publicGarageService->indexableVehicles()->count();

        $this->line('Vehicles:              '.$total);
        $this->line('Public:                '.$public);
        $this->line('Hidden:                '.$hidden);
        $this->line('Outreach demo excluded: '.$outreachDemoExcluded);
        $this->line('Generated slugs:       '.$generatedSlugs);
        $this->line('Legacy published:      '.$legacyPublished);
        $this->line('Indexable:             '.$indexable);
        $this->line('Added to sitemap:      '.$sitemapCount);
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
