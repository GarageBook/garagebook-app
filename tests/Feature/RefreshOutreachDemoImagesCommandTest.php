<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefreshOutreachDemoImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_attaches_images_to_existing_outreach_demo_vehicle_without_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'is_outreach_demo' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'display_variant' => 'Garage demo',
            'nickname' => 'Bestaande demo motor',
            'current_km' => 18750,
            'distance_unit' => 'km',
            'year' => 2023,
            'is_public' => true,
            'share_costs_publicly' => true,
            'share_attachments_publicly' => true,
        ]);

        $sourceDirectory = sys_get_temp_dir() . '/refresh-outreach-demo-images-' . Str::random(8);
        File::ensureDirectoryExists($sourceDirectory);
        File::put($sourceDirectory . '/01-bike.jpg', 'demo-jpg');
        File::put($sourceDirectory . '/02-bike.webp', 'demo-webp');

        $this->artisan('garagebook:refresh-outreach-demo-images', [
            '--path' => $sourceDirectory,
        ])->assertSuccessful();

        $vehicle->refresh();

        $this->assertSame('vehicle-photos/outreach-demo-vehicle-' . $vehicle->id . '-01-01-bike.jpg', $vehicle->photo);
        $this->assertSame([
            'vehicle-photos/outreach-demo-vehicle-' . $vehicle->id . '-02-02-bike.webp',
        ], $vehicle->photos);
        Storage::disk('public')->assertExists($vehicle->photo);
        Storage::disk('public')->assertExists($vehicle->photos[0]);

        File::deleteDirectory($sourceDirectory);
    }
}
