<?php

namespace Tests\Feature;

use App\Models\TripLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TripPhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_private_trip_photo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Ducati',
            'model' => 'Multistrada V4',
        ]);

        Storage::disk('local')->put('trip-photos/1/1/photo-a.jpg', 'photo-body');

        $trip = TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Veluwerit',
            'ridden_at' => '2026-05-18',
            'source_file_path' => 'trip-uploads/trip.gpx',
            'source_format' => 'gpx',
            'photos' => ['trip-photos/1/1/photo-a.jpg'],
        ]);

        $response = $this->actingAs($user)
            ->get(route('trip-photos.show', ['trip' => $trip, 'photoIndex' => 0]));

        $response->assertOk();

        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_other_user_cannot_open_private_trip_photo(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Ducati',
            'model' => 'Multistrada V4',
        ]);

        Storage::disk('local')->put('trip-photos/1/1/photo-b.jpg', 'photo-body');

        $trip = TripLog::query()->create([
            'user_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Ardennenrit',
            'ridden_at' => '2026-05-18',
            'source_file_path' => 'trip-uploads/trip.gpx',
            'source_format' => 'gpx',
            'photos' => ['trip-photos/1/1/photo-b.jpg'],
        ]);

        $this->actingAs($otherUser)
            ->get(route('trip-photos.show', ['trip' => $trip, 'photoIndex' => 0]))
            ->assertNotFound();
    }

    public function test_user_cannot_open_nonexistent_trip_photo_index(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Moto Guzzi',
            'model' => 'V85 TT',
        ]);

        $trip = TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Dijkrit',
            'ridden_at' => '2026-05-18',
            'source_file_path' => 'trip-uploads/trip.gpx',
            'source_format' => 'gpx',
            'photos' => ['trip-photos/1/1/photo-c.jpg'],
        ]);

        $this->actingAs($user)
            ->get(route('trip-photos.show', ['trip' => $trip, 'photoIndex' => 1]))
            ->assertNotFound();
    }
}
