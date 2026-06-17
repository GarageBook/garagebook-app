<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Outreach\OutreachDemoService;
use Illuminate\Console\Command;

class RefreshOutreachDemoImagesCommand extends Command
{
    protected $signature = 'garagebook:refresh-outreach-demo-images
        {--path=/temp/3 : Bronmap met demo-afbeeldingen}
        {--force : Overschrijf bestaande demo-vehicle images met de bronmap}';

    protected $description = 'Voorzie bestaande outreach demo-voertuigen veilig van demo-afbeeldingen.';

    public function handle(OutreachDemoService $outreachDemoService): int
    {
        $sourcePath = trim((string) $this->option('path'));
        $force = (bool) $this->option('force');
        $storageLinkExists = is_link(public_path('storage')) || is_dir(public_path('storage'));

        $this->info('Bronmap: ' . $sourcePath);
        $this->info('Force mode: ' . ($force ? 'ja' : 'nee'));
        $this->info('Storage symlink aanwezig: ' . ($storageLinkExists ? 'ja' : 'nee'));

        $users = User::query()
            ->where('is_outreach_demo', true)
            ->with(['vehicles' => fn ($query) => $query
                ->where('brand', 'Yamaha')
                ->where('model', 'MT-07')
                ->where('display_variant', 'Garage demo')])
            ->get();

        $processedVehicles = 0;
        $totalImported = 0;
        $vehiclesWithoutImages = 0;

        foreach ($users as $user) {
            foreach ($user->vehicles as $vehicle) {
                $result = $outreachDemoService->refreshVehicleDemoImages($vehicle, $sourcePath, $force);

                $processedVehicles++;
                $totalImported += $result['imported_count'];

                if ($result['final_image_count'] === 0) {
                    $vehiclesWithoutImages++;
                }

                $this->line(sprintf(
                    'user_id=%d vehicle_id=%d gevonden_bestanden=%s geimporteerde_bestanden=%s finale_image_count=%d',
                    $user->id,
                    $vehicle->id,
                    json_encode($result['source_filenames'], JSON_UNESCAPED_SLASHES),
                    json_encode($result['imported_paths'], JSON_UNESCAPED_SLASHES),
                    $result['final_image_count'],
                ));
            }
        }

        $this->info('Verwerkte demo-voertuigen: ' . $processedVehicles);
        $this->info('Totaal geimporteerde afbeeldingen: ' . $totalImported);
        $this->info('Demo-voertuigen zonder beelden na run: ' . $vehiclesWithoutImages);

        if (! $storageLinkExists) {
            $this->error('De public/storage symlink ontbreekt. Draai php artisan storage:link op live.');

            return self::FAILURE;
        }

        if ($vehiclesWithoutImages > 0) {
            $this->error('Niet alle outreach demo-voertuigen hebben beelden na deze run.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
