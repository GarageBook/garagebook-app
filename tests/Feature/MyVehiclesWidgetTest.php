<?php

namespace Tests\Feature;

use App\Filament\Widgets\MyVehicles;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyVehiclesWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_vehicle_widget_renders_gallery_controls_for_multiple_photos(): void
    {
        $owner = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
            'nickname' => 'Allroad',
            'current_km' => 45500,
            'photo' => 'vehicle-photos/primary.jpg',
            'photos' => ['vehicle-photos/detail.jpg'],
            'purchase_price' => 9000,
            'insurance_cost_per_month' => 25,
            'road_tax_cost_per_month' => 11,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Grote beurt',
            'km_reading' => 45000,
            'maintenance_date' => now()->subMonth()->toDateString(),
            'cost' => '325.00',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->subWeeks(2)->toDateString(),
            'distance_km' => 280,
            'fuel_liters' => 16,
            'price_per_liter' => 2.00,
        ]);

        $this->actingAs($owner);

        Livewire::test(MyVehicles::class)
            ->assertSeeText('Mijn voertuigen')
            ->assertSeeText('Allroad')
            ->assertSeeText('Totale kosten')
            ->assertSeeText('Per maand')
            ->assertSeeText('EUR 9.357,00')
            ->assertSeeText('EUR 68,00')
            ->assertSeeHtml('aria-label="Vorige foto"')
            ->assertSeeHtml('aria-label="Volgende foto"')
            ->assertSeeHtml('aria-label="Open fotogalerij"');
    }

    public function test_dashboard_vehicle_widget_does_not_render_another_users_vehicle(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Ducati',
            'model' => 'Monster',
            'nickname' => 'Rosso',
            'current_km' => 12000,
        ]);

        $this->actingAs($otherUser);

        Livewire::test(MyVehicles::class)
            ->assertSeeText('Mijn voertuigen')
            ->assertSeeText('Geen voertuigen toegevoegd')
            ->assertDontSeeText('Ducati Monster')
            ->assertDontSeeText('Rosso');
    }
}
