<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAuthorityIndex;
use App\Services\VehicleAuthorityIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleAuthoritySyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private function regularUser(): User
    {
        return User::factory()->create(['is_outreach_demo' => false]);
    }

    private function demoUser(): User
    {
        return User::factory()->outreachDemo()->create();
    }

    private function publicVehicle(User $user, string $brand, string $model, array $extra = []): Vehicle
    {
        return Vehicle::query()->create(array_merge([
            'user_id' => $user->id,
            'brand' => $brand,
            'model' => $model,
            'is_public' => true,
            'public_slug' => "{$brand}-{$model}-{$user->id}",
        ], $extra));
    }

    // -------------------------------------------------------------------------
    // Basic sync
    // -------------------------------------------------------------------------

    public function test_command_exits_successfully(): void
    {
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();
    }

    public function test_command_creates_index_entry_for_public_vehicle(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07');

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $entry = VehicleAuthorityIndex::where('slug', 'yamaha-mt-07')->first();
        $this->assertNotNull($entry);
        $this->assertSame('Yamaha', $entry->brand);
        $this->assertSame('MT-07', $entry->model);
        $this->assertSame(1, $entry->public_vehicle_count);
        $this->assertTrue($entry->is_indexable);
    }

    public function test_command_creates_entry_with_correct_vehicle_count(): void
    {
        $userA = $this->regularUser();
        $userB = $this->regularUser();

        $this->publicVehicle($userA, 'Honda', 'CB500F', ['public_slug' => 'honda-a']);
        $this->publicVehicle($userB, 'Honda', 'CB500F', ['public_slug' => 'honda-b']);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $entry = VehicleAuthorityIndex::where('slug', 'honda-cb500f')->first();
        $this->assertSame(2, $entry->vehicle_count);
        $this->assertSame(2, $entry->public_vehicle_count);
    }

    // -------------------------------------------------------------------------
    // Outreach demo exclusion
    // -------------------------------------------------------------------------

    public function test_command_excludes_demo_vehicles_from_public_count(): void
    {
        $demo = $this->demoUser();
        $real = $this->regularUser();

        $this->publicVehicle($demo, 'Kawasaki', 'Z900', ['public_slug' => 'demo-z900']);
        $this->publicVehicle($real, 'Kawasaki', 'Z900', ['public_slug' => 'real-z900']);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $entry = VehicleAuthorityIndex::where('slug', 'kawasaki-z900')->first();
        $this->assertNotNull($entry);
        $this->assertSame(1, $entry->public_vehicle_count);
        $this->assertSame(1, $entry->vehicle_count);
        $this->assertTrue($entry->is_indexable);
    }

    public function test_command_does_not_create_entry_when_only_demo_vehicles_exist(): void
    {
        $demo = $this->demoUser();
        $this->publicVehicle($demo, 'Suzuki', 'GSX-R600', ['public_slug' => 'demo-gsxr']);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $this->assertNull(VehicleAuthorityIndex::where('slug', 'suzuki-gsx-r600')->first());
    }

    // -------------------------------------------------------------------------
    // Indexability
    // -------------------------------------------------------------------------

    public function test_entry_is_not_indexable_when_no_public_vehicles(): void
    {
        $user = $this->regularUser();
        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R1250GS',
            'is_public' => false,
            'public_slug' => null,
        ]);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $entry = VehicleAuthorityIndex::where('slug', 'bmw-r1250gs')->first();
        $this->assertNotNull($entry);
        $this->assertSame(0, $entry->public_vehicle_count);
        $this->assertFalse($entry->is_indexable);
    }

    public function test_entry_becomes_indexable_after_vehicle_goes_public(): void
    {
        $user = $this->regularUser();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Triumph',
            'model' => 'Tiger 900',
            'is_public' => false,
            'public_slug' => null,
        ]);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $entry = VehicleAuthorityIndex::where('slug', 'triumph-tiger-900')->first();
        $this->assertFalse($entry->is_indexable);

        // Make vehicle public
        $vehicle->update(['is_public' => true, 'public_slug' => 'triumph-tiger900-real']);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $entry->refresh();
        $this->assertTrue($entry->is_indexable);
        $this->assertSame(1, $entry->public_vehicle_count);
    }

    // -------------------------------------------------------------------------
    // Idempotency and update tracking
    // -------------------------------------------------------------------------

    public function test_command_is_idempotent_and_skips_unchanged_entries(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07');

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $this->artisan('garagebook:vehicle-authority:sync')
            ->assertSuccessful()
            ->expectsOutputToContain('Skipped:   1');
    }

    public function test_command_updates_existing_entry_when_count_changes(): void
    {
        $userA = $this->regularUser();
        $this->publicVehicle($userA, 'Yamaha', 'MT-07', ['public_slug' => 'mt07-a']);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $this->assertSame(1, VehicleAuthorityIndex::where('slug', 'yamaha-mt-07')->value('public_vehicle_count'));

        $userB = $this->regularUser();
        $this->publicVehicle($userB, 'Yamaha', 'MT-07', ['public_slug' => 'mt07-b']);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $this->assertSame(2, VehicleAuthorityIndex::where('slug', 'yamaha-mt-07')->value('public_vehicle_count'));
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    public function test_all_model_slugs_returns_slugs_sorted_by_public_vehicle_count_desc(): void
    {
        $u1 = $this->regularUser();
        $u2 = $this->regularUser();
        $u3 = $this->regularUser();

        $this->publicVehicle($u1, 'Honda', 'CB500F', ['public_slug' => 'honda-1']);
        $this->publicVehicle($u2, 'Yamaha', 'MT-07', ['public_slug' => 'yamaha-1']);
        $this->publicVehicle($u3, 'Yamaha', 'MT-07', ['public_slug' => 'yamaha-2']);

        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $slugs = app(VehicleAuthorityIndexService::class)->allIndexableSlugs();

        $this->assertSame('yamaha-mt-07', $slugs->first(), 'Yamaha MT-07 has more vehicles and should come first');
    }

    // -------------------------------------------------------------------------
    // Dry run
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_write_to_database(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Honda', 'CB500F', ['public_slug' => 'honda-dry']);

        $this->artisan('garagebook:vehicle-authority:sync --dry-run')->assertSuccessful();

        $this->assertDatabaseMissing('vehicle_authority_index', ['slug' => 'honda-cb500f']);
    }

    // -------------------------------------------------------------------------
    // Summary output
    // -------------------------------------------------------------------------

    public function test_command_outputs_summary_lines(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07');

        $this->artisan('garagebook:vehicle-authority:sync')
            ->assertSuccessful()
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:')
            ->expectsOutputToContain('Skipped:')
            ->expectsOutputToContain('Indexable:')
            ->expectsOutputToContain('Hidden:');
    }
}
