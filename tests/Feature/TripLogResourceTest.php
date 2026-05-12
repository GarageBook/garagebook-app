<?php

namespace Tests\Feature;

use App\Filament\Resources\TripLogs\Pages\CreateTripLog;
use App\Filament\Resources\TripLogs\Pages\ListTripLogs;
use App\Filament\Resources\TripLogs\TripLogResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Jobs\ProcessTripLogUpload;
use App\Models\TripLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Trips\TripParserManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TripLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_upload_and_processing_creates_processed_trip(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
        ]);

        $fixturePath = base_path('tests/Fixtures/trips/sample.gpx');
        $uploadedFile = UploadedFile::fake()->create('sample.gpx', 10, 'application/gpx+xml');

        $this->actingAs($user);

        Livewire::test(CreateTripLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'title' => 'Ardennenrit',
                'description' => 'Testtrip',
                'source_file_path' => $uploadedFile,
                'source_format' => 'gpx',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $trip = TripLog::query()->firstOrFail();
        Storage::disk('local')->put($trip->source_file_path, file_get_contents($fixturePath));

        Queue::assertPushed(ProcessTripLogUpload::class);
        $this->assertSame(TripLog::STATUS_PENDING, $trip->status);

        (new ProcessTripLogUpload($trip->id))->handle(app(TripParserManager::class));

        $trip->refresh();

        $this->assertSame($user->id, $trip->user_id);
        $this->assertSame($vehicle->id, $trip->vehicle_id);
        $this->assertSame(TripLog::STATUS_PROCESSED, $trip->status);
        $this->assertNotNull($trip->distance_km);
        $this->assertSame(4, $trip->points_count);
        $this->assertNotNull($trip->geojson);
        $this->assertNotNull($trip->simplified_geojson);

        $this->get(TripLogResource::getUrl('view', ['record' => $trip]))
            ->assertOk()
            ->assertSeeText('Tripoverzicht')
            ->assertSeeText('Routekaart')
            ->assertSee('trip-route-map-'.$trip->id)
            ->assertSee('build/assets/app-');
    }

    public function test_trip_resource_query_only_returns_authenticated_users_trips(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Yamaha',
            'model' => 'Tenere 700',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
        ]);

        $ownerTrip = TripLog::query()->create([
            'user_id' => $owner->id,
            'vehicle_id' => $ownerVehicle->id,
            'source_file_path' => 'trip-uploads/test.gpx',
            'source_format' => 'gpx',
        ]);

        TripLog::query()->create([
            'user_id' => $otherUser->id,
            'vehicle_id' => $otherVehicle->id,
            'source_file_path' => 'trip-uploads/other.gpx',
            'source_format' => 'gpx',
        ]);

        $this->actingAs($owner);

        $this->assertSame([$ownerTrip->id], TripLogResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_user_cannot_open_other_users_trip_view_or_edit_page(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'KTM',
            'model' => '890 Adventure',
        ]);

        $trip = TripLog::query()->create([
            'user_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'source_file_path' => 'trip-uploads/test.gpx',
            'source_format' => 'gpx',
        ]);

        $this->actingAs($otherUser)
            ->get(TripLogResource::getUrl('view', ['record' => $trip]))
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->get(TripLogResource::getUrl('edit', ['record' => $trip]))
            ->assertNotFound();
    }

    public function test_vehicle_pages_show_only_the_owners_trips(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'BMW',
            'model' => 'R 1300 GS',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Honda',
            'model' => 'Africa Twin',
        ]);

        $ownerTrip = TripLog::query()->create([
            'user_id' => $owner->id,
            'vehicle_id' => $ownerVehicle->id,
            'title' => 'Eigen trip',
            'source_file_path' => 'trip-uploads/owner.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        TripLog::query()->create([
            'user_id' => $otherUser->id,
            'vehicle_id' => $otherVehicle->id,
            'title' => 'Verborgen trip',
            'source_file_path' => 'trip-uploads/other.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        $this->actingAs($owner)
            ->get(VehicleResource::getUrl('view', ['record' => $ownerVehicle]))
            ->assertOk()
            ->assertSeeText('Recente trips')
            ->assertSeeText($ownerTrip->title)
            ->assertDontSeeText('Verborgen trip');

        $this->actingAs($owner)
            ->get(VehicleResource::getUrl('edit', ['record' => $ownerVehicle]))
            ->assertOk()
            ->assertSeeText('Recente trips')
            ->assertSeeText($ownerTrip->title)
            ->assertDontSeeText('Verborgen trip');
    }

    public function test_invalid_gpx_marks_trip_as_failed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom 650',
        ]);

        Storage::disk('local')->put('trip-uploads/invalid.gpx', '<gpx><trk></trk></gpx>');

        $trip = TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'source_file_path' => 'trip-uploads/invalid.gpx',
            'source_file_name' => 'invalid.gpx',
            'source_format' => 'gpx',
        ]);

        ProcessTripLogUpload::dispatchSync($trip->id);

        $trip->refresh();

        $this->assertSame(TripLog::STATUS_FAILED, $trip->status);
        $this->assertNotNull($trip->failure_reason);
    }

    public function test_reprocess_action_resets_the_trip_and_dispatches_a_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Triumph',
            'model' => 'Tiger 900',
        ]);

        $trip = TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Herverwerken',
            'source_file_path' => 'trip-uploads/reprocess.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_FAILED,
            'failure_reason' => 'Te weinig punten',
        ]);

        $this->actingAs($user);

        Livewire::test(ListTripLogs::class)
            ->callTableAction('reprocess', $trip);

        $trip->refresh();

        $this->assertSame(TripLog::STATUS_PENDING, $trip->status);
        $this->assertNull($trip->failure_reason);
        Queue::assertPushed(ProcessTripLogUpload::class, fn (ProcessTripLogUpload $job) => $job->tripLogId === $trip->id);
    }
}
