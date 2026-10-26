<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Support\PublicSeoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicSitemapFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://app.garagebook.nl');
    }

    public function test_garage_lastmod_follows_public_content_without_changing_on_a_read(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F800GS',
            'year' => 2018,
            'is_public' => true,
        ]);
        $slug = $vehicle->public_slug;

        $sitemapVehicle = app(PublicGarageService::class)->indexableVehicles()->first();
        $this->assertFalse($sitemapVehicle->relationLoaded('maintenanceLogs'));
        $this->assertSame('2026-05-01T12:00:00+00:00', $this->lastmodFor($slug));

        $this->travelTo(Carbon::parse('2026-05-05 13:00:00', 'UTC'));
        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie en filter vervangen',
            'km_reading' => 0,
            'maintenance_date' => '2026-05-05',
        ]);
        $this->assertSame('2026-05-05T13:00:00+00:00', $this->lastmodFor($slug));

        $this->travelTo(Carbon::parse('2026-05-07 14:00:00', 'UTC'));
        $log->update(['description' => 'Olie, filter en remmen vervangen']);
        $this->assertSame('2026-05-07T14:00:00+00:00', $this->lastmodFor($slug));

        $this->travelTo(Carbon::parse('2026-05-09 15:00:00', 'UTC'));
        $log->delete();
        $this->assertSame('2026-05-09T15:00:00+00:00', $this->lastmodFor($slug));

        $this->travelTo(Carbon::parse('2026-05-10 16:00:00', 'UTC'));
        $this->assertSame('2026-05-09T15:00:00+00:00', $this->lastmodFor($slug));

        $this->travelTo(Carbon::parse('2026-05-11 17:00:00', 'UTC'));
        $vehicle->update(['model' => 'F800GS Adventure']);
        $this->assertSame('2026-05-11T17:00:00+00:00', $this->lastmodFor($slug));

        $this->travelBack();
    }

    public function test_moving_a_log_updates_the_old_public_garage(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 10:00:00', 'UTC'));
        $user = User::factory()->create();
        $first = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F800GS',
            'is_public' => true,
        ]);
        $second = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R1250GS',
            'is_public' => true,
        ]);
        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $first->id,
            'description' => 'Remmen gecontroleerd',
            'km_reading' => 1000,
            'maintenance_date' => '2026-06-01',
        ]);

        $this->travelTo(Carbon::parse('2026-06-02 11:00:00', 'UTC'));
        $log->update(['vehicle_id' => $second->id]);

        $this->assertSame('2026-06-02T11:00:00+00:00', $this->lastmodFor($first->public_slug));
        $this->assertSame('2026-06-02T11:00:00+00:00', $this->lastmodFor($second->public_slug));
        $this->travelBack();
    }

    public function test_sitemaps_exclude_private_deleted_and_demo_vehicles_and_are_not_cached(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CB500',
            'year' => 2020,
            'is_public' => true,
        ]);
        $url = PublicSeoUrl::garage($vehicle->public_slug);

        $this->get('/sitemap-garages.xml')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee($url, false);

        $vehicle->update(['is_public' => false]);
        $this->get('/sitemap-garages.xml')->assertDontSee($url, false);

        $vehicle->update(['is_public' => true]);
        $this->get('/sitemap-garages.xml')->assertSee($url, false);

        $vehicle->delete();
        $this->get('/sitemap-garages.xml')->assertDontSee($url, false);

        $demoUser = User::factory()->outreachDemo()->create();
        $demo = Vehicle::query()->create([
            'user_id' => $demoUser->id,
            'brand' => 'KTM',
            'model' => 'Duke',
            'is_public' => true,
        ]);
        $this->get('/sitemap-garages.xml')->assertDontSee(PublicSeoUrl::garage($demo->public_slug), false);

        $this->get('/sitemap-vehicle-authority.xml')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/sitemap-onderhoud.xml')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    private function lastmodFor(string $slug): string
    {
        $response = $this->get('/sitemap-garages.xml')->assertOk();
        $url = preg_quote(PublicSeoUrl::garage($slug), '#');
        $this->assertSame(1, preg_match('#<loc>'.$url.'</loc>\\s*<lastmod>([^<]+)</lastmod>#', $response->getContent(), $matches));

        return $matches[1];
    }
}
