<?php

namespace Tests\Feature;

use App\Enums\LifecycleState;
use App\Filament\Auth\Register;
use App\Models\LifecycleStateEntry;
use App\Models\MaintenanceLog;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Lifecycle\LifecycleProgressSyncService;
use App\Services\Outreach\OutreachDemoService;
use App\Support\AnalyticsAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Marktplaats2026FunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_marktplaats2026_tracking_link_opens_canonical_demo_and_keeps_mp001_attribution(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $demoVehicle = $this->createExistingPhotographedDemoVehicle();
        $queryString = $this->marktplaatsQueryString('MP001');

        $startResponse = $this->get('/start?'.$queryString);

        $prospect = $this->marktplaatsProspect('MP001');
        $startResponse->assertRedirect('/demo/garage/'.$prospect->token.'?'.$queryString);

        $demoResponse = $this->get('/demo/garage/'.$prospect->token.'?'.$queryString);

        $demoResponse->assertRedirect('/admin/tijdlijn?vehicle_id='.$demoVehicle->id);
        $this->assertSame($demoVehicle->user_id, $prospect->refresh()->user_id);
        $this->assertSame([
            'source' => 'marktplaats',
            'campaign_slug' => 'marktplaats2026',
            'prospect_id' => 'MP001',
            'utm_source' => 'marktplaats',
            'utm_medium' => 'outreach',
            'utm_campaign' => 'marktplaats2026',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));

        $this->assertDatabaseHas('outreach_campaigns', [
            'slug' => 'marktplaats2026',
        ]);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'email_link_opened',
        ]);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'demo_login_completed',
        ]);

        $this->get('/admin/tijdlijn?vehicle_id='.$demoVehicle->id)
            ->assertOk()
            ->assertSeeText('Voorbeeld onderhoudshistorie')
            ->assertSeeText('Voorbeeld GarageBook')
            ->assertSeeText('Zo kan de onderhoudshistorie van jouw motor eruitzien')
            ->assertSeeText("Verzamel onderhoud, kilometerstanden, facturen en foto's op één plek en deel de historie overzichtelijk met een potentiële koper.")
            ->assertSeeText('Maak gratis een GarageBook voor mijn motor')
            ->assertSee('prospect_id=MP001', false)
            ->assertSee('campaign_slug=marktplaats2026', false)
            ->assertSee('source=marktplaats', false);
    }

    public function test_marktplaats2026_never_shows_b2b_demo_identity_from_canonical_vehicle_or_prospect(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $demoVehicle = $this->createExistingPhotographedDemoVehicle('Demo motor voor Tayomoto Motor & Onderhoud');
        $queryString = $this->marktplaatsQueryString('MP001');

        $this->get('/start?'.$queryString)->assertRedirect();
        $prospect = $this->marktplaatsProspect('MP001');
        $this->get('/demo/garage/'.$prospect->token.'?'.$queryString)
            ->assertRedirect('/admin/tijdlijn?vehicle_id='.$demoVehicle->id);

        $this->assertSame('MP001', $prospect->company_name);
        $this->assertSame('marktplaats:MP001', $prospect->website);

        $this->get('/admin/tijdlijn?vehicle_id='.$demoVehicle->id)
            ->assertOk()
            ->assertSeeText('Voorbeeld onderhoudshistorie')
            ->assertSeeText('Voorbeeld GarageBook')
            ->assertSeeText('Zo kan de onderhoudshistorie van jouw motor eruitzien')
            ->assertSeeText('Maak gratis een GarageBook voor mijn motor')
            ->assertDontSeeText('Demo motor voor Tayomoto Motor & Onderhoud')
            ->assertDontSeeText('Tayomoto')
            ->assertDontSeeText('Motor & Onderhoud')
            ->assertDontSeeText('voor MP001')
            ->assertDontSeeText('Club2026')
            ->assertDontSeeText('Workshop2026');

        $this->get('/admin/vehicles/create')
            ->assertOk()
            ->assertSeeText('Maak gratis een GarageBook voor mijn motor')
            ->assertSee('prospect_id=MP001', false)
            ->assertSee('campaign_slug=marktplaats2026', false)
            ->assertSee('source=marktplaats', false)
            ->assertDontSeeText('Demo motor voor Tayomoto Motor & Onderhoud')
            ->assertDontSeeText('Tayomoto')
            ->assertDontSeeText('Motor & Onderhoud')
            ->assertDontSeeText('Club2026')
            ->assertDontSeeText('Workshop2026');

        $registerUrl = app(OutreachDemoService::class)
            ->marktplaats2026DemoContextForAuthenticatedUser()['register_url'];

        $this->get($registerUrl)
            ->assertOk()
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY, [
                'source' => 'marktplaats',
                'campaign_slug' => 'marktplaats2026',
                'prospect_id' => 'MP001',
                'utm_source' => 'marktplaats',
                'utm_medium' => 'outreach',
                'utm_campaign' => 'marktplaats2026',
                'landing_page' => '/start',
                'demo_user_id' => (string) $demoVehicle->user_id,
                'outreach_prospect_id' => (string) $prospect->id,
                'intended' => 'vehicle_create',
            ])
            ->assertDontSeeText('Demo motor voor Tayomoto Motor & Onderhoud')
            ->assertDontSeeText('Tayomoto')
            ->assertDontSeeText('Motor & Onderhoud')
            ->assertDontSeeText('Club2026')
            ->assertDontSeeText('Workshop2026');
    }

    public function test_all_marktplaats2026_links_resolve_to_consumer_demo_context_without_b2b_identity(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $demoVehicle = $this->createExistingPhotographedDemoVehicle('Demo motor voor Tayomoto Motor & Onderhoud');

        foreach (range(1, 24) as $number) {
            $prospectId = sprintf('MP%03d', $number);
            $queryString = $this->marktplaatsQueryString($prospectId);

            auth()->logout();
            $this->flushSession();

            $this->get('/start?'.$queryString)->assertRedirect();
            $prospect = $this->marktplaatsProspect($prospectId);

            $this->assertSame($prospectId, $prospect->company_name);
            $this->assertSame('marktplaats:'.$prospectId, $prospect->website);
            $this->assertSame('marktplaats', $prospect->source);

            $this->get('/demo/garage/'.$prospect->token.'?'.$queryString)
                ->assertRedirect('/admin/tijdlijn?vehicle_id='.$demoVehicle->id);

            $this->assertSame([
                'source' => 'marktplaats',
                'campaign_slug' => 'marktplaats2026',
                'prospect_id' => $prospectId,
                'utm_source' => 'marktplaats',
                'utm_medium' => 'outreach',
                'utm_campaign' => 'marktplaats2026',
                'landing_page' => '/start',
            ], session(AnalyticsAttribution::SESSION_KEY));

            $this->get('/admin/tijdlijn?vehicle_id='.$demoVehicle->id)
                ->assertOk()
                ->assertSeeText('Voorbeeld onderhoudshistorie')
                ->assertSeeText('Voorbeeld GarageBook')
                ->assertSee('prospect_id='.$prospectId, false)
                ->assertSee('campaign_slug=marktplaats2026', false)
                ->assertSee('source=marktplaats', false)
                ->assertDontSeeText('Demo motor voor Tayomoto Motor & Onderhoud')
                ->assertDontSeeText('Tayomoto')
                ->assertDontSeeText('Motor & Onderhoud')
                ->assertDontSeeText('voor '.$prospectId)
                ->assertDontSeeText('Club2026')
                ->assertDontSeeText('Workshop2026');
        }
    }

    public function test_mp002_remains_separate_from_mp001(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->createExistingPhotographedDemoVehicle();

        $this->get('/start?'.$this->marktplaatsQueryString('MP001'))->assertRedirect();
        $this->get('/start?'.$this->marktplaatsQueryString('MP002'))->assertRedirect();

        $mp001 = $this->marktplaatsProspect('MP001');
        $mp002 = $this->marktplaatsProspect('MP002');

        $this->assertNotSame($mp001->id, $mp002->id);
        $this->assertNotSame($mp001->token, $mp002->token);
        $this->assertSame('marktplaats:MP001', $mp001->website);
        $this->assertSame('marktplaats:MP002', $mp002->website);
    }

    public function test_registration_preserves_marktplaats2026_and_prospect_attribution(): void
    {
        session()->start();

        $this->get('/register?'.http_build_query([
            'source' => 'marktplaats',
            'campaign_slug' => 'marktplaats2026',
            'prospect_id' => 'MP001',
            'utm_source' => 'marktplaats',
            'utm_medium' => 'outreach',
            'utm_campaign' => 'marktplaats2026',
            'demo_user_id' => 123,
            'outreach_prospect_id' => 456,
            'intended' => 'vehicle_create',
        ]))->assertOk();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Marktplaats Signup',
                'email' => 'marktplaats-signup@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'marktplaats-signup@example.com')->firstOrFail();

        $this->assertSame('marktplaats', $user->registration_source);
        $this->assertDatabaseHas('user_attributions', [
            'user_id' => $user->id,
            'source' => 'marktplaats',
            'campaign_slug' => 'marktplaats2026',
            'prospect_id' => 'MP001',
            'utm_source' => 'marktplaats',
            'utm_medium' => 'outreach',
            'utm_campaign' => 'marktplaats2026',
            'demo_user_id' => 123,
            'outreach_prospect_id' => 456,
            'intended' => 'vehicle_create',
            'landing_page' => '/register',
        ]);
    }

    public function test_vehicle_added_and_first_maintenance_logged_are_linkable_to_same_funnel_user(): void
    {
        $user = User::factory()->create([
            'email' => 'marktplaats-lifecycle@example.com',
            'registration_source' => 'marktplaats',
        ]);

        $user->attribution()->create([
            'source' => 'marktplaats',
            'campaign_slug' => 'marktplaats2026',
            'prospect_id' => 'MP001',
            'utm_source' => 'marktplaats',
            'utm_medium' => 'outreach',
            'utm_campaign' => 'marktplaats2026',
        ]);

        app(LifecycleProgressSyncService::class)->syncUser($user);
        $this->assertCurrentState($user, LifecycleState::REGISTERED);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'current_km' => 34665,
            'distance_unit' => 'km',
            'is_public' => false,
        ]);

        $this->assertCurrentState($user, LifecycleState::VEHICLE_ADDED);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Eerste onderhoudslog',
            'maintenance_date' => now()->toDateString(),
            'km_reading' => 34680,
        ]);

        $this->assertCurrentState($user, LifecycleState::FIRST_MAINTENANCE_LOGGED);
        $this->assertDatabaseHas('user_attributions', [
            'user_id' => $user->id,
            'source' => 'marktplaats',
            'campaign_slug' => 'marktplaats2026',
            'prospect_id' => 'MP001',
        ]);
    }

    public function test_club2026_start_flow_still_uses_growth_partner_demo(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $demoVehicle = $this->createExistingPhotographedDemoVehicle();
        $queryString = 'utm_source=aprilia-riders-association&utm_medium=partner&utm_campaign=club2026&partner_slug=aprilia-riders-association&campaign_slug=club2026';

        $response = $this->get('/start?'.$queryString);

        $prospect = OutreachProspect::query()
            ->where('source', 'growth_partner')
            ->where('website', 'growth-partner:aprilia-riders-association')
            ->firstOrFail();

        $response->assertRedirect('/demo/garage/'.$prospect->token.'?'.$queryString);
        $this->get('/demo/garage/'.$prospect->token.'?'.$queryString)
            ->assertRedirect('/admin/tijdlijn?vehicle_id='.$demoVehicle->id);

        $this->assertDatabaseHas('outreach_campaigns', [
            'slug' => 'growth-club2026',
        ]);
        $this->assertSame([
            'campaign_slug' => 'club2026',
            'partner_slug' => 'aprilia-riders-association',
            'utm_source' => 'aprilia-riders-association',
            'utm_medium' => 'partner',
            'utm_campaign' => 'club2026',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_existing_yamaha_demo_without_marktplaats2026_context_is_unchanged(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $demoVehicle = $this->createExistingPhotographedDemoVehicle();
        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Bestaande demo',
            'user_id' => null,
        ]);

        $this->get('/demo/garage/'.$prospect->token)
            ->assertRedirect('/admin/tijdlijn?vehicle_id='.$demoVehicle->id);

        $this->get('/admin/tijdlijn?vehicle_id='.$demoVehicle->id)
            ->assertOk()
            ->assertSeeText('Yamaha MT-07')
            ->assertSeeText('Voorjaarsservice met bewijsbestand')
            ->assertDontSeeText('Zo kan de onderhoudshistorie van jouw motor eruitzien')
            ->assertDontSeeText('Maak gratis een GarageBook voor mijn motor');
    }

    public function test_mp001_local_end_to_end_funnel_is_measurable(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        session()->start();

        $demoVehicle = $this->createExistingPhotographedDemoVehicle();
        $queryString = $this->marktplaatsQueryString('MP001');

        $this->get('/start?'.$queryString)->assertRedirect();
        $prospect = $this->marktplaatsProspect('MP001');
        $this->get('/demo/garage/'.$prospect->token.'?'.$queryString)
            ->assertRedirect('/admin/tijdlijn?vehicle_id='.$demoVehicle->id);

        $timeline = $this->get('/admin/tijdlijn?vehicle_id='.$demoVehicle->id);
        $timeline->assertOk()
            ->assertSeeText('Zo kan de onderhoudshistorie van jouw motor eruitzien')
            ->assertSeeText('Maak gratis een GarageBook voor mijn motor');

        $registerUrl = app(OutreachDemoService::class)
            ->marktplaats2026DemoContextForAuthenticatedUser()['register_url'];

        $this->get($registerUrl)->assertOk();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'MP001 Testuser',
                'email' => 'mp001-testuser@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'mp001-testuser@example.com')->firstOrFail();
        app(LifecycleProgressSyncService::class)->syncUser($user);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'current_km' => 34665,
            'distance_unit' => 'km',
            'is_public' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Eerste onderhoudslog MP001',
            'maintenance_date' => now()->toDateString(),
            'km_reading' => 34680,
        ]);

        $this->assertDatabaseHas('outreach_campaigns', [
            'slug' => 'marktplaats2026',
        ]);
        $this->assertDatabaseHas('outreach_prospects', [
            'id' => $prospect->id,
            'source' => 'marktplaats',
            'website' => 'marktplaats:MP001',
            'user_id' => $demoVehicle->user_id,
        ]);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'email_link_opened',
        ]);
        $this->assertDatabaseHas('outreach_events', [
            'outreach_prospect_id' => $prospect->id,
            'event_type' => 'demo_login_completed',
        ]);
        $this->assertDatabaseHas('user_attributions', [
            'user_id' => $user->id,
            'source' => 'marktplaats',
            'campaign_slug' => 'marktplaats2026',
            'prospect_id' => 'MP001',
            'utm_source' => 'marktplaats',
            'utm_medium' => 'outreach',
            'utm_campaign' => 'marktplaats2026',
            'demo_user_id' => $demoVehicle->user_id,
            'outreach_prospect_id' => $prospect->id,
            'intended' => 'vehicle_create',
        ]);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
        ]);
        $this->assertDatabaseHas('maintenance_logs', [
            'vehicle_id' => $vehicle->id,
            'description' => 'Eerste onderhoudslog MP001',
        ]);
        $this->assertCurrentState($user, LifecycleState::FIRST_MAINTENANCE_LOGGED);
    }

    private function marktplaatsQueryString(string $prospectId): string
    {
        return http_build_query([
            'utm_source' => 'marktplaats',
            'utm_medium' => 'outreach',
            'utm_campaign' => 'marktplaats2026',
            'campaign_slug' => 'marktplaats2026',
            'source' => 'marktplaats',
            'prospect_id' => $prospectId,
        ]);
    }

    private function marktplaatsProspect(string $prospectId): OutreachProspect
    {
        return OutreachProspect::query()
            ->where('source', 'marktplaats')
            ->where('website', 'marktplaats:'.$prospectId)
            ->firstOrFail();
    }

    private function assertCurrentState(User $user, LifecycleState $state): void
    {
        $this->assertDatabaseHas('lifecycle_state_entries', [
            'user_id' => $user->id,
            'state' => $state->value,
            'exited_at' => null,
        ]);

        $this->assertSame(1, LifecycleStateEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('exited_at')
            ->count());
    }

    private function createExistingPhotographedDemoVehicle(string $nickname = 'Existing photographed Yamaha MT-07 demo'): Vehicle
    {
        Storage::disk('public')->put('vehicle-photos/existing-yamaha-mt-07-primary.jpg', 'primary-photo');
        Storage::disk('public')->put('vehicle-photos/existing-yamaha-mt-07-detail.jpg', 'detail-photo');

        $user = User::factory()->outreachDemo()->create([
            'name' => 'GarageBook demo',
            'email' => 'photographed-outreach-demo@garagebook.nl',
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'display_variant' => 'Garage demo',
            'nickname' => $nickname,
            'current_km' => 18750,
            'distance_unit' => 'km',
            'year' => 2023,
            'public_slug' => 'working-yamaha-mt-07-demo',
            'is_public' => true,
            'share_costs_publicly' => true,
            'share_attachments_publicly' => true,
            'photo' => 'vehicle-photos/existing-yamaha-mt-07-primary.jpg',
            'photos' => ['vehicle-photos/existing-yamaha-mt-07-detail.jpg'],
            'notes' => 'Existing photographed demo dataset for outreach and partner flows.',
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
                'notes' => 'Existing photographed Yamaha MT-07 demo-data.',
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
