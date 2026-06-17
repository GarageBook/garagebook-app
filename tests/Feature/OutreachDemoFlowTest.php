<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OutreachDemoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_url_uses_live_app_host_and_exact_route_path(): void
    {
        $prospect = OutreachProspect::factory()->create([
            'token' => 'demo-token-1234567890',
        ]);

        $this->assertSame(
            'https://app.garagebook.nl/demo/garage/demo-token-1234567890',
            $prospect->demoUrl(),
        );

        $this->assertSame(
            'https://app.garagebook.nl' . route('outreach.demo.login', ['token' => $prospect->token], false),
            $prospect->demoUrl(),
        );
    }

    public function test_demo_link_marks_prospect_clicked_creates_demo_user_and_public_vehicle_page(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Moto Haarlem',
            'user_id' => null,
        ]);

        $response = $this->get('/demo/garage/' . $prospect->token);

        $prospect->refresh();
        $user = $prospect->user;
        $vehicle = $user?->vehicles()->first();

        $response->assertRedirect('/admin/vehicles/' . $vehicle?->id);
        $this->assertNotNull($prospect->clicked_at);
        $this->assertNotNull($prospect->first_login_at);
        $this->assertSame(1, $prospect->login_count);
        $this->assertTrue($user?->is_outreach_demo ?? false);
        $this->assertFalse($user?->isAdmin() ?? true);
        $this->assertNotNull($vehicle);
        $this->assertTrue($vehicle->is_public);
        $this->assertNotNull($vehicle->public_slug);
        $this->assertCount(3, $vehicle->maintenanceLogs);
        $this->assertSame(1, $vehicle->documents()->count());

        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'email_link_opened',
        ]);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'demo_login_completed',
        ]);

        $this->get('/garage/' . $vehicle->public_slug)
            ->assertOk()
            ->assertSeeText('Yamaha MT-07')
            ->assertSeeText('Voorjaarsservice met bewijsbestand');
    }

    public function test_second_click_reuses_same_demo_user_and_increments_login_count(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Moto Utrecht',
            'user_id' => null,
        ]);

        $this->get('/demo/garage/' . $prospect->token)->assertRedirect();

        $prospect->refresh();
        $userId = $prospect->user_id;
        $vehicleCount = $prospect->user->vehicles()->count();
        $maintenanceCount = MaintenanceLog::query()->whereHas('vehicle', fn ($query) => $query->where('user_id', $userId))->count();

        $this->get('/demo/garage/' . $prospect->token)->assertRedirect();

        $prospect->refresh();

        $this->assertSame($userId, $prospect->user_id);
        $this->assertSame(2, $prospect->login_count);
        $this->assertSame($vehicleCount, $prospect->user->vehicles()->count());
        $this->assertSame($maintenanceCount, MaintenanceLog::query()->whereHas('vehicle', fn ($query) => $query->where('user_id', $userId))->count());
    }

    public function test_invalid_demo_token_returns_not_found(): void
    {
        $this->get('/demo/garage/invalid-token')->assertNotFound();
    }

    public function test_demo_user_only_sees_own_demo_data(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $otherUser = User::factory()->create();
        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Ducati',
            'model' => 'Monster',
            'current_km' => 9000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Andere gebruiker beurt',
            'maintenance_date' => now()->subDay()->toDateString(),
            'km_reading' => 9000,
        ]);

        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Moto Demo Scope',
        ]);

        $this->get('/demo/garage/' . $prospect->token)->assertRedirect();

        $this->get('/admin/vehicles')
            ->assertOk()
            ->assertSeeText('Yamaha')
            ->assertDontSeeText('Ducati');

        $this->get('/admin/maintenance-logs')
            ->assertOk()
            ->assertSeeText('Voorjaarsservice met bewijsbestand')
            ->assertDontSeeText('Andere gebruiker beurt');
    }
}
