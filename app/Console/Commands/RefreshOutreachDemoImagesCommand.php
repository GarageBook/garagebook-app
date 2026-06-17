<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Outreach\OutreachDemoService;
use Illuminate\Console\Command;

class RefreshOutreachDemoImagesCommand extends Command
{
    protected $signature = 'garagebook:refresh-outreach-demo-images
        {--path=/temp/3 : Bronmap met demo-afbeeldingen}';

    protected $description = 'Voorzie bestaande outreach demo-voertuigen veilig van demo-afbeeldingen.';

    public function handle(OutreachDemoService $outreachDemoService): int
    {
        $sourcePath = trim((string) $this->option('path'));

        $users = User::query()
            ->where('is_outreach_demo', true)
            ->with(['vehicles' => fn ($query) => $query
                ->where('brand', 'Yamaha')
                ->where('model', 'MT-07')
                ->where('display_variant', 'Garage demo')])
            ->get();

        $processedVehicles = 0;
        $totalImported = 0;

        foreach ($users as $user) {
            foreach ($user->vehicles as $vehicle) {
                $result = $outreachDemoService->refreshVehicleDemoImages($vehicle, $sourcePath);

                $processedVehicles++;
                $totalImported += $result['imported_count'];

                $this->line(sprintf(
                    'Demo-user %d voertuig %d: bronmap gevonden=%s, gevonden=%d, toegevoegd=%d',
                    $user->id,
                    $vehicle->id,
                    $result['source_found'] ? 'ja' : 'nee',
                    $result['found_count'],
                    $result['imported_count'],
                ));
            }
        }

        $this->info('Verwerkte demo-voertuigen: ' . $processedVehicles);
        $this->info('Totaal toegevoegde afbeeldingen: ' . $totalImported);

        return self::SUCCESS;
    }
}
