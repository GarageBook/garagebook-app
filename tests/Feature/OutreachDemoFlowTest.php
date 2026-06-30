<?php

namespace Tests\Feature;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\MaintenanceLog;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Outreach\OutreachDemoService;
use App\Support\AnalyticsAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
            'https://app.garagebook.nl'.route('outreach.demo.login', ['token' => $prospect->token], false),
            $prospect->demoUrl(),
        );
    }

    public function test_canonical_demo_vehicle_resolver_returns_yamaha_with_media(): void
    {
        Storage::fake('public');

        $canonicalVehicle = $this->createCanonicalDemoVehicle();

        $resolvedVehicle = app(OutreachDemoService::class)->getCanonicalDemoVehicle();

        $this->assertSame($canonicalVehicle->id, $resolvedVehicle->id);
        $this->assertSame(OutreachDemoService::CANONICAL_DEMO_VEHICLE_PUBLIC_SLUG, $resolvedVehicle->public_slug);
        $this->assertSame('Yamaha', $resolvedVehicle->brand);
        $this->assertSame('MT-07', $resolvedVehicle->model);
        $this->assertSame('vehicle-photos/canonical-yamaha-mt-07-primary.jpg', $resolvedVehicle->photo);
        Storage::disk('public')->assertExists($resolvedVehicle->photo);
    }

    public function test_canonical_demo_vehicle_resolver_fails_hard_when_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Canonical outreach demo vehicle is missing.');

        app(OutreachDemoService::class)->getCanonicalDemoVehicle();
    }

    public function test_growth_partner_start_url_redirects_to_existing_demo_flow_and_keeps_attribution(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $canonicalVehicle = $this->createCanonicalDemoVehicle();
        $queryString = 'source=partner&campaign_slug=club2026&partner_slug=motorclub-x&utm_source=motorclub-x&utm_medium=partner&utm_campaign=club2026';

        $response = $this->get('/start?'.$queryString);

        $prospect = OutreachProspect::query()
            ->where('source', 'growth_partner')
            ->where('website', 'growth-partner:motorclub-x')
            ->firstOrFail();

        $response->assertRedirect('/demo/garage/'.$prospect->token.'?'.$queryString);

        $this->assertDatabaseHas('outreach_campaigns', [
            'slug' => 'growth-club2026',
        ]);
        $this->assertSame('motorclub-x', $prospect->company_name);
        $this->assertSame([
            'source' => 'partner',
            'campaign_slug' => 'club2026',
            'partner_slug' => 'motorclub-x',
            'utm_source' => 'motorclub-x',
            'utm_medium' => 'partner',
            'utm_campaign' => 'club2026',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));

        $this->assertSame(1, Vehicle::query()->count());

        $this->get('/demo/garage/'.$prospect->token.'?'.$queryString)
            ->assertRedirect('/admin/tijdlijn?vehicle_id='.$canonicalVehicle->id);

        $prospect->refresh();

        $this->assertSame($canonicalVehicle->user_id, $prospect->user_id);
        $this->assertSame(1, Vehicle::query()->count());
        $this->assertSame($canonicalVehicle->id, $prospect->user?->vehicles()->firstOrFail()->id);
        $this->assertSame([
            'source' => 'partner',
            'campaign_slug' => 'club2026',
            'partner_slug' => 'motorclub-x',
            'utm_source' => 'motorclub-x',
            'utm_medium' => 'partner',
            'utm_campaign' => 'club2026',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_club2026_demo_uses_canonical_media_without_fallback_marketing_image(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $canonicalVehicle = $this->createCanonicalDemoVehicle();
        $queryString = 'source=partner&campaign_slug=club2026&partner_slug=motorclub-x&utm_source=motorclub-x&utm_medium=partner&utm_campaign=club2026';

        $this->get('/start?'.$queryString)->assertRedirect();
        $prospect = OutreachProspect::query()->where('website', 'growth-partner:motorclub-x')->firstOrFail();

        $this->get('/demo/garage/'.$prospect->token.'?'.$queryString)->assertRedirect();

        $this->get('/garage/'.$canonicalVehicle->public_slug)
            ->assertOk()
            ->assertSee('storage/vehicle-photos/canonical-yamaha-mt-07-primary.jpg', false)
            ->assertDontSee('garagebook-hero-workshop-motor.webp', false);

        $this->actingAs($canonicalVehicle->user)
            ->get(VehicleResource::getUrl('create'))
            ->assertOk()
            ->assertSeeText('Start gratis')
            ->assertSee('outreach_prospect_id='.$prospect->id, false);
    }

    public function test_demo_link_marks_prospect_clicked_and_reuses_canonical_public_vehicle_page(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $canonicalVehicle = $this->createCanonicalDemoVehicle();
        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Moto Haarlem',
            'user_id' => null,
        ]);

        $response = $this->get('/demo/garage/'.$prospect->token);

        $prospect->refresh();
        $user = $prospect->user;
        $vehicle = $user?->vehicles()->first();

        $response->assertRedirect('/admin/tijdlijn?vehicle_id='.$canonicalVehicle->id);
        $this->assertNotNull($prospect->clicked_at);
        $this->assertNotNull($prospect->first_login_at);
        $this->assertSame(1, $prospect->login_count);
        $this->assertTrue($user?->is_outreach_demo ?? false);
        $this->assertFalse($user?->isAdmin() ?? true);
        $this->assertSame($canonicalVehicle->id, $vehicle?->id);
        $this->assertTrue($vehicle?->is_public ?? false);
        $this->assertSame(OutreachDemoService::CANONICAL_DEMO_VEHICLE_PUBLIC_SLUG, $vehicle?->public_slug);
        $this->assertCount(3, $vehicle?->maintenanceLogs ?? []);

        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'email_link_opened',
        ]);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'demo_login_completed',
        ]);

        $this->get('/garage/'.$canonicalVehicle->public_slug)
            ->assertOk()
            ->assertSeeText('Yamaha MT-07')
            ->assertSeeText('Voorjaarsservice met bewijsbestand')
            ->assertSee('storage/'.$canonicalVehicle->photo, false);
    }

    public function test_second_click_reuses_same_demo_user_and_does_not_create_vehicle(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $canonicalVehicle = $this->createCanonicalDemoVehicle();
        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Moto Utrecht',
            'user_id' => null,
        ]);

        $this->get('/demo/garage/'.$prospect->token)->assertRedirect();

        $prospect->refresh();
        $userId = $prospect->user_id;
        $vehicleCount = Vehicle::query()->count();
        $maintenanceCount = MaintenanceLog::query()->where('vehicle_id', $canonicalVehicle->id)->count();

        $this->get('/demo/garage/'.$prospect->token)->assertRedirect();

        $prospect->refresh();

        $this->assertSame($userId, $prospect->user_id);
        $this->assertSame(2, $prospect->login_count);
        $this->assertSame($vehicleCount, Vehicle::query()->count());
        $this->assertSame($maintenanceCount, MaintenanceLog::query()->where('vehicle_id', $canonicalVehicle->id)->count());
    }

    public function test_existing_outreach_demo_uses_same_canonical_vehicle(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $canonicalVehicle = $this->createCanonicalDemoVehicle();
        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Bestaande outreach demo',
            'user_id' => null,
        ]);

        $this->get('/demo/garage/'.$prospect->token)
            ->assertRedirect('/admin/tijdlijn?vehicle_id='.$canonicalVehicle->id);

        $this->assertSame($canonicalVehicle->user_id, $prospect->refresh()->user_id);
        $this->assertSame(1, Vehicle::query()->count());
    }

    public function test_regular_start_and_geratel_registration_remain_available(): void
    {
        $this->get('/start?utm_source=google&utm_medium=cpc')
            ->assertRedirect('/admin/register?utm_source=google&utm_medium=cpc');

        $this->get('/admin/register/geratel?utm_source=geratel&utm_medium=partner')
            ->assertOk()
            ->assertSee('garagebook-geratel-verified.png', false);
    }

    public function test_invalid_demo_token_returns_not_found(): void
    {
        $this->get('/demo/garage/invalid-token')->assertNotFound();
    }

    public function test_demo_user_only_sees_own_demo_data(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->createCanonicalDemoVehicle();
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

        $this->get('/demo/garage/'.$prospect->token)->assertRedirect();

        $this->get('/admin/vehicles')
            ->assertOk()
            ->assertSeeText('Yamaha')
            ->assertDontSeeText('Ducati');

        $this->get('/admin/maintenance-logs')
            ->assertOk()
            ->assertSeeText('Voorjaarsservice met bewijsbestand')
            ->assertDontSeeText('Andere gebruiker beurt');
    }

    private function createCanonicalDemoVehicle(): Vehicle
    {
        Storage::disk('public')->put('vehicle-photos/canonical-yamaha-mt-07-primary.jpg', 'primary-photo');
        Storage::disk('public')->put('vehicle-photos/canonical-yamaha-mt-07-detail.jpg', 'detail-photo');

        $user = User::factory()->outreachDemo()->create([
            'name' => 'GarageBook demo',
            'email' => 'outreach-demo@garagebook.nl',
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'display_variant' => 'Garage demo',
            'nickname' => 'Canonical Yamaha MT-07 demo',
            'current_km' => 18750,
            'distance_unit' => 'km',
            'year' => 2023,
            'public_slug' => OutreachDemoService::CANONICAL_DEMO_VEHICLE_PUBLIC_SLUG,
            'is_public' => true,
            'share_costs_publicly' => true,
            'share_attachments_publicly' => true,
            'photo' => 'vehicle-photos/canonical-yamaha-mt-07-primary.jpg',
            'photos' => ['vehicle-photos/canonical-yamaha-mt-07-detail.jpg'],
            'notes' => 'Canonical demo dataset for outreach and partner flows.',
        ]);

        foreach ([
            ['Afleverbeurt en software-check', 13200, '219.00', now()->subMonths(8)->toDateString()],
            ['Jaarbeurt met kettingsetcontrole', 15980, '348.50', now()->subMonths(4)->toDateString()],
            ['Voorjaarsservice met bewijsbestand', 18420, '289.95', now()->subWeeks(6)->toDateString()],
        ] as [$description, $kmReading, $cost, $date]) {
            MaintenanceLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'description' => $description,
                'maintenance_date' => $date,
                'km_reading' => $kmReading,
                'cost' => $cost,
                'notes' => 'Canonical Yamaha MT-07 demo-data.',
                'attachments' => $description === 'Voorjaarsservice met bewijsbestand' ? [$vehicle->photo] : [],
                'media_attachments' => $description === 'Voorjaarsservice met bewijsbestand' ? [$vehicle->photo] : [],
                'file_attachments' => [],
                'share_attachments_publicly' => true,
                'hide_photos_on_public_page' => false,
            ]);
        }

        return $vehicle->refresh();
    }
}
