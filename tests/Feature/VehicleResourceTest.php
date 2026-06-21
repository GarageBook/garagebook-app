<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AnalyticsEventTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_can_open_vehicle_create_page_and_create_vehicle(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(VehicleResource::getUrl('create'))
            ->assertOk()
            ->assertSeeText(__('vehicles.create_title'))
            ->assertDontSeeText('Je kijkt nu rond in een demo-account');

        Livewire::actingAs($user)
            ->test(CreateVehicle::class)
            ->fillForm([
                'brand' => 'Suzuki',
                'model' => 'V-Strom 800',
                'current_km' => 1200,
                'distance_unit' => 'km',
            ])
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(MaintenanceLogResource::getUrl('create', [
                'vehicle_id' => Vehicle::query()->where('brand', 'Suzuki')->value('id'),
                'onboarding' => 1,
            ]));

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $user->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom 800',
        ]);
    }

    public function test_outreach_demo_user_sees_vehicle_create_blockade_and_cta(): void
    {
        $user = User::factory()->outreachDemo()->create();
        $prospect = OutreachProspect::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(VehicleResource::getUrl('create'));

        $response
            ->assertOk()
            ->assertSeeText('Leuk dat je een voertuig wilt toevoegen')
            ->assertSeeText('Je kijkt nu rond in een demo-account.')
            ->assertSeeText('Gratis account aanmaken')
            ->assertSeeText('Terug naar demo')
            ->assertSee('source=outreach_demo', false)
            ->assertSee('demo_user_id='.$user->id, false)
            ->assertSee('outreach_prospect_id='.$prospect->id, false)
            ->assertSee('intended=vehicle_create', false);
    }

    public function test_outreach_demo_user_cannot_create_vehicle_via_livewire_action(): void
    {
        $user = User::factory()->outreachDemo()->create();

        Livewire::actingAs($user)
            ->test(CreateVehicle::class)
            ->fillForm([
                'brand' => 'Honda',
                'model' => 'Transalp',
                'current_km' => 2000,
                'distance_unit' => 'km',
            ])
            ->call('create');

        $this->assertDatabaseMissing('vehicles', [
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
        ]);
    }

    public function test_outreach_demo_register_cta_contains_source_and_demo_user_id(): void
    {
        $user = User::factory()->outreachDemo()->create();

        $this->actingAs($user)
            ->get(VehicleResource::getUrl('create'))
            ->assertOk()
            ->assertSee('source=outreach_demo', false)
            ->assertSee('demo_user_id='.$user->id, false)
            ->assertSee('intended=vehicle_create', false);
    }

    public function test_outreach_demo_create_blockade_renders_analytics_tracking(): void
    {
        $user = User::factory()->outreachDemo()->create();

        $this->actingAs($user)
            ->get(VehicleResource::getUrl('create'))
            ->assertOk()
            ->assertSee('data-analytics-event="outreach_demo_register_cta_clicked"', false)
            ->assertSee('data-analytics-param-demo-user-id="'.$user->id.'"', false)
            ->assertSee('data-analytics-param-intended="vehicle_create"', false);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSame('outreach_demo_vehicle_create_blocked', $events[0]['name'] ?? null);
        $this->assertSame($user->id, $events[0]['params']['demo_user_id'] ?? null);
        $this->assertSame('vehicle_create', $events[0]['params']['intended'] ?? null);
    }
}
