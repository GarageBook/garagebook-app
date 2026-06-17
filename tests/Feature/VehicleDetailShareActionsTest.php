<?php

namespace Tests\Feature;

use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class VehicleDetailShareActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_detail_header_actions_include_public_page_and_pdf_export(): void
    {
        $user = User::factory()->create([
            'name' => 'Willem Garage',
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
            'nickname' => 'Allroad',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(ViewVehicle::class, ['record' => $vehicle->getRouteKey()]);

        $method = new ReflectionMethod($component->instance(), 'getHeaderActions');
        $method->setAccessible(true);
        $actions = collect($method->invoke($component->instance()))->keyBy(fn ($action) => $action->getName());

        $this->assertSame(
            url('/garage/bmw-r-1200-gs'),
            $actions->get('openSharePage')->getUrl(),
        );
        $this->assertSame(
            url('/maintenance/pdf?vehicle_id='.$vehicle->id),
            $actions->get('exportPdf')->getUrl(),
        );
        $this->assertNull($actions->get('copyUrl'));
    }

    public function test_vehicle_detail_page_renders_share_actions_with_expected_labels_and_tracking(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        $response = $this->actingAs($user)
            ->get(VehicleResource::getUrl('view', ['record' => $vehicle]));

        $response->assertOk()
            ->assertSeeText('Open publieke voertuigpagina')
            ->assertSeeText('Download onderhoudsboekje')
            ->assertSee('data-analytics-event="public_share_created"', false)
            ->assertSee('data-analytics-param-source="share"', false)
            ->assertSee('data-analytics-param-source="export"', false)
            ->assertDontSeeText('Kopieer publieke link');
    }

    public function test_vehicle_detail_share_action_routes_load_without_server_error(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Tourfiets',
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->get('/garage/'.$vehicle->public_slug)
            ->assertOk();

        $this->actingAs($user)
            ->get('/maintenance/pdf?vehicle_id='.$vehicle->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
