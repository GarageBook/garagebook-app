<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\MaintenanceMediaOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceMediaOptimizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_optimizer_rewrites_png_attachment_to_jpg_and_updates_log(): void
    {
        Storage::fake('public');

        $image = imagecreatetruecolor(3200, 1800);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 200, 60));

        $sourcePath = Storage::disk('public')->path('maintenance-attachments/test-image.png');

        if (! is_dir(dirname($sourcePath))) {
            mkdir(dirname($sourcePath), 0777, true);
        }

        imagepng($image, $sourcePath);
        imagedestroy($image);

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Africa Twin',
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test',
            'km_reading' => 1000,
            'maintenance_date' => now()->toDateString(),
            'attachments' => ['maintenance-attachments/test-image.png'],
        ]);

        app(MaintenanceMediaOptimizer::class)->optimizeLog($log, 2200, 82);

        $log->refresh();

        $this->assertSame(['maintenance-attachments/test-image.jpg'], $log->attachments);
        Storage::disk('public')->assertExists('maintenance-attachments/test-image.jpg');
        Storage::disk('public')->assertMissing('maintenance-attachments/test-image.png');
    }
}
