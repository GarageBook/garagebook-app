<?php

namespace Tests\Feature;

use App\Filament\Resources\FuelLogs\FuelLogResource;
use App\Filament\Resources\FuelLogs\Pages\CreateFuelLog;
use App\Filament\Resources\FuelLogs\Pages\EditFuelLog;
use App\Filament\Resources\FuelLogs\Pages\ListFuelLogs;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Filament\Widgets\FuelConsumptionOverview;
use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EvPhevFuelLogSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_vehicle_and_consumption_pages_render_for_ev_phev_smoke(): void
    {
        $user = User::factory()->create();
        $vehicles = $this->createSmokeVehicles($user);

        $this->actingAs($user)
            ->get(VehicleResource::getUrl('create'))
            ->assertOk()
            ->assertSeeText('Aandrijvingstype');

        $this->actingAs($user)
            ->get(VehicleResource::getUrl('edit', ['record' => $vehicles['electric']->id]))
            ->assertOk()
            ->assertSeeText('Aandrijvingstype')
            ->assertSeeText('Standaard thuislaadtarief');

        $this->actingAs($user)
            ->get(ListFuelLogs::getUrl())
            ->assertOk()
            ->assertSeeText('Verbruik');

        $this->actingAs($user)
            ->get(FuelLogResource::getUrl('create', ['vehicle_id' => $vehicles['petrol']->id]))
            ->assertOk()
            ->assertSeeText('Aantal liter brandstof')
            ->assertDontSeeText('kWh geladen');

        $this->actingAs($user)
            ->get(FuelLogResource::getUrl('create', ['vehicle_id' => $vehicles['hybrid']->id]))
            ->assertOk()
            ->assertSeeText('Aantal liter brandstof')
            ->assertDontSeeText('kWh geladen');

        $this->actingAs($user)
            ->get(FuelLogResource::getUrl('create', ['vehicle_id' => $vehicles['electric']->id]))
            ->assertOk()
            ->assertSeeText('kWh geladen')
            ->assertSeeText('Laadtype')
            ->assertDontSeeText('Aantal liter brandstof');

        $this->actingAs($user)
            ->get(FuelLogResource::getUrl('create', ['vehicle_id' => $vehicles['phev']->id]))
            ->assertOk()
            ->assertSeeText('Type registratie')
            ->assertSeeText('Tankbeurt')
            ->assertSeeText('Laadmoment')
            ->assertSeeText('Gecombineerd');
    }

    public function test_ev_phev_consumption_create_edit_list_and_widget_smoke(): void
    {
        $user = User::factory()->create();
        $vehicles = $this->createSmokeVehicles($user);

        $petrolLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['petrol']->id,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-01',
            'odometer_km' => 1000,
            'distance_km' => 250,
            'fuel_liters' => 14,
            'price_per_liter' => 2.10,
        ]);
        $hybridLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['hybrid']->id,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-01',
            'odometer_km' => 2000,
            'distance_km' => 500,
            'fuel_liters' => 24,
            'price_per_liter' => 2.00,
        ]);
        $firstEvLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['electric']->id,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-01',
            'odometer_km' => 10000,
            'energy_kwh' => 35,
            'price_per_kwh' => 0.42,
            'charge_type' => FuelLog::CHARGE_TYPE_PUBLIC_AC,
            'total_cost' => 14.70,
        ]);
        $homeEvLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['electric']->id,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-02',
            'odometer_km' => 10200,
            'energy_kwh' => 38,
            'charge_type' => FuelLog::CHARGE_TYPE_HOME,
        ]);
        $sameOdometerEvLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['electric']->id,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-03',
            'odometer_km' => 10200,
            'energy_kwh' => 10,
            'charge_type' => FuelLog::CHARGE_TYPE_OTHER,
        ]);
        $manualCostMissingRateLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['electric']->id,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-04',
            'odometer_km' => 10400,
            'energy_kwh' => 36,
            'charge_type' => FuelLog::CHARGE_TYPE_PUBLIC_AC,
            'total_cost' => 18.00,
        ]);

        $phevFuelLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['phev']->id,
            'entry_type' => FuelLog::ENTRY_TYPE_FUEL,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-01',
            'odometer_km' => 5000,
            'distance_km' => 300,
            'fuel_liters' => 16,
            'price_per_liter' => 2.05,
        ]);
        $phevChargeLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['phev']->id,
            'entry_type' => FuelLog::ENTRY_TYPE_CHARGE,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-02',
            'odometer_km' => 5120,
            'energy_kwh' => 11,
            'charge_type' => FuelLog::CHARGE_TYPE_HOME,
        ]);
        $phevCombinedLog = $this->createFuelLog($user, [
            'vehicle_id' => $vehicles['phev']->id,
            'entry_type' => FuelLog::ENTRY_TYPE_COMBINED,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => '2026-06-03',
            'odometer_km' => 5300,
            'distance_km' => 180,
            'fuel_liters' => 7,
            'price_per_liter' => 2.00,
            'energy_kwh' => 9,
            'price_per_kwh' => 0.45,
            'charge_type' => FuelLog::CHARGE_TYPE_PUBLIC_AC,
        ]);

        $this->assertSame('11.40', $homeEvLog->fresh()->total_cost);
        $this->assertSame('3.19', $phevChargeLog->fresh()->total_cost);
        $this->assertNull($sameOdometerEvLog->fresh()->total_cost);
        $this->assertSame('18.00', $manualCostMissingRateLog->fresh()->total_cost);

        foreach ([$petrolLog, $hybridLog, $firstEvLog, $homeEvLog, $phevFuelLog, $phevChargeLog, $phevCombinedLog] as $log) {
            Livewire::actingAs($user)
                ->test(EditFuelLog::class, ['record' => $log->getRouteKey()])
                ->fillForm($this->editPayloadFor($log->fresh()))
                ->call('save')
                ->assertHasNoFormErrors();
        }

        Livewire::actingAs($user)
            ->test(CreateFuelLog::class)
            ->fillForm([
                'vehicle_id' => $vehicles['phev']->id,
                'entry_type' => FuelLog::ENTRY_TYPE_COMBINED,
                'distance_unit' => DistanceUnitService::UNIT_KM,
                'fuel_date' => '2026-06-05',
                'odometer_km' => 5400,
                'distance_km' => 100,
                'fuel_liters' => 4,
                'price_per_liter' => 2.00,
                'charge_type' => FuelLog::CHARGE_TYPE_PUBLIC_AC,
            ])
            ->call('create')
            ->assertHasFormErrors(['energy_kwh']);

        $this->actingAs($user)
            ->get(ListFuelLogs::getUrl(['vehicle_id' => $vehicles['petrol']->id]))
            ->assertOk()
            ->assertSeeText('14,00 L')
            ->assertSeeText('EUR 29,40')
            ->assertDontSeeText('kWh');

        $this->actingAs($user)
            ->get(ListFuelLogs::getUrl(['vehicle_id' => $vehicles['electric']->id]))
            ->assertOk()
            ->assertSeeText('Laadmoment')
            ->assertSeeText('kWh/100 km')
            ->assertSeeText('Laadkosten')
            ->assertDontSeeText('NaN')
            ->assertDontSeeText('division by zero');

        $this->actingAs($user)
            ->get(ListFuelLogs::getUrl(['vehicle_id' => $vehicles['phev']->id]))
            ->assertOk()
            ->assertSeeText('Tankbeurt')
            ->assertSeeText('Laadmoment')
            ->assertSeeText('Gecombineerd')
            ->assertSeeText('7,00 L + 9,00 kWh')
            ->assertDontSeeText('NaN')
            ->assertDontSeeText('division by zero');

        Livewire::actingAs($user)
            ->test(FuelConsumptionOverview::class)
            ->assertOk()
            ->assertDontSeeText('NaN')
            ->assertDontSeeText('division by zero');
    }

    /**
     * @return array<string, Vehicle>
     */
    private function createSmokeVehicles(User $user): array
    {
        return [
            'petrol' => Vehicle::query()->create([
                'user_id' => $user->id,
                'brand' => 'Mazda',
                'model' => 'MX-5',
                'powertrain_type' => Vehicle::POWERTRAIN_PETROL,
            ]),
            'hybrid' => Vehicle::query()->create([
                'user_id' => $user->id,
                'brand' => 'Toyota',
                'model' => 'Prius',
                'powertrain_type' => Vehicle::POWERTRAIN_HYBRID,
            ]),
            'electric' => Vehicle::query()->create([
                'user_id' => $user->id,
                'brand' => 'Tesla',
                'model' => 'Model Y',
                'powertrain_type' => Vehicle::POWERTRAIN_ELECTRIC,
                'home_kwh_rate' => 0.30,
            ]),
            'phev' => Vehicle::query()->create([
                'user_id' => $user->id,
                'brand' => 'Volvo',
                'model' => 'XC60 T8',
                'powertrain_type' => Vehicle::POWERTRAIN_PHEV,
                'home_kwh_rate' => 0.29,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createFuelLog(User $user, array $payload): FuelLog
    {
        Livewire::actingAs($user)
            ->test(CreateFuelLog::class)
            ->fillForm($payload)
            ->call('create')
            ->assertHasNoFormErrors();

        return FuelLog::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayloadFor(FuelLog $log): array
    {
        return [
            'vehicle_id' => $log->vehicle_id,
            'entry_type' => $log->entry_type,
            'distance_unit' => DistanceUnitService::UNIT_KM,
            'fuel_date' => $log->fuel_date?->toDateString(),
            'odometer_km' => $log->odometer_km,
            'distance_km' => $log->distance_km,
            'fuel_liters' => $log->fuel_liters,
            'price_per_liter' => $log->price_per_liter,
            'energy_kwh' => $log->energy_kwh,
            'price_per_kwh' => $log->price_per_kwh,
            'total_cost' => $log->total_cost,
            'charge_type' => $log->charge_type,
            'station_location' => $log->station_location,
            'notes' => $log->notes,
        ];
    }
}
