<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublishAllVehiclesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_missing_slugs_and_respects_hidden_vehicles(): void
    {
        $user = User::factory()->create();
        $demoUser = User::factory()->outreachDemo()->create();

        $publicVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'year' => 2019,
            'is_public' => true,
        ]);

        $hiddenVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'year' => 2001,
            'is_public' => false,
        ]);

        $demoVehicle = Vehicle::query()->create([
            'user_id' => $demoUser->id,
            'brand' => 'Kawasaki',
            'model' => 'Z650',
            'year' => 2022,
            'is_public' => true,
        ]);

        DB::table('vehicles')
            ->whereIn('id', [$publicVehicle->id, $hiddenVehicle->id, $demoVehicle->id])
            ->update(['public_slug' => null]);

        $this->artisan('garagebook:publish-all-vehicles')
            ->expectsOutput('Vehicles:              3')
            ->expectsOutput('Public:                2')
            ->expectsOutput('Hidden:                1')
            ->expectsOutput('Outreach demo excluded: 1')
            ->expectsOutput('Generated slugs:       3')
            ->expectsOutput('Indexable:             1')
            ->expectsOutput('Added to sitemap:      1')
            ->expectsOutput('Done.')
            ->assertSuccessful();

        $publicVehicle->refresh();
        $hiddenVehicle->refresh();
        $demoVehicle->refresh();

        $this->assertNotEmpty($publicVehicle->public_slug);
        $this->assertNotEmpty($hiddenVehicle->public_slug);
        $this->assertNotEmpty($demoVehicle->public_slug);
        $this->assertTrue((bool) $publicVehicle->is_public);
        $this->assertFalse((bool) $hiddenVehicle->is_public);
        $this->assertTrue(app(PublicGarageService::class)->shouldIndex($publicVehicle->fresh(['user', 'maintenanceLogs'])));
        $this->assertFalse(app(PublicGarageService::class)->shouldIndex($hiddenVehicle->fresh(['user', 'maintenanceLogs'])));
        $this->assertFalse(app(PublicGarageService::class)->shouldIndex($demoVehicle->fresh(['user', 'maintenanceLogs'])));

        $this->get('/sitemap-garages.xml')
            ->assertOk()
            ->assertSee(url('/garage/'.$publicVehicle->public_slug), false)
            ->assertDontSee(url('/garage/'.$hiddenVehicle->public_slug), false)
            ->assertDontSee(url('/garage/'.$demoVehicle->public_slug), false);
    }
}
