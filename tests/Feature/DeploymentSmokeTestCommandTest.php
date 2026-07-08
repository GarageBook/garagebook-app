<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeploymentSmokeTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_smoke_test_passes_for_known_public_routes(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        Schema::dropIfExists('notifications');

        User::factory()->admin()->create();
        $vehicle = $this->createPublicVehicle();

        $this->artisan('garagebook:deployment-smoke-test')
            ->expectsOutputToContain('Deployment smoke test')
            ->expectsOutputToContain('✓ public home: /')
            ->expectsOutputToContain('✓ admin dashboard: /admin')
            ->expectsOutputToContain('✓ seo health dashboard: /admin/seo-health-dashboard')
            ->expectsOutputToContain('✓ public garage page: /garage/'.$vehicle->public_slug)
            ->expectsOutputToContain('✓ sitemap garages: /sitemap-garages.xml')
            ->expectsOutputToContain('PASS')
            ->assertExitCode(0);
    }

    public function test_deployment_smoke_test_uses_configured_admin_email_and_garage_slug(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        Config::set('services.deployment_smoke_test.admin_email', 'WillemVanVeelen@ICloud.Com');
        Config::set('services.deployment_smoke_test.public_garage_slug', 'configured-public-garage');
        Schema::dropIfExists('notifications');

        User::factory()->create([
            'email' => 'WillemVanVeelen@ICloud.Com',
            'is_admin' => false,
        ]);

        Vehicle::query()->create([
            'user_id' => User::factory()->create()->id,
            'brand' => 'Honda',
            'model' => 'Gold Wing',
            'year' => 2026,
            'public_slug' => 'configured-public-garage',
            'is_public' => true,
        ]);

        $this->artisan('garagebook:deployment-smoke-test')
            ->expectsOutputToContain('✓ admin dashboard: /admin')
            ->expectsOutputToContain('✓ seo health dashboard: /admin/seo-health-dashboard')
            ->expectsOutputToContain('✓ public garage page: /garage/configured-public-garage')
            ->expectsOutputToContain('PASS')
            ->assertExitCode(0);
    }

    public function test_deployment_smoke_test_fails_when_a_route_returns_non_200(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');

        User::factory()->admin()->create();

        $this->artisan('garagebook:deployment-smoke-test', [
            '--garage-slug' => 'missing-public-slug',
        ])
            ->expectsOutputToContain('✗ public garage page: /garage/missing-public-slug returned 404')
            ->expectsOutputToContain('FAILED')
            ->assertExitCode(1);
    }

    public function test_deployment_smoke_test_fails_when_no_indexable_public_garage_exists(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        Schema::dropIfExists('notifications');

        User::factory()->admin()->create();
        Vehicle::query()->create([
            'user_id' => User::factory()->create()->id,
            'brand' => 'Honda',
            'model' => 'NC750X',
            'year' => 2026,
            'public_slug' => '2026-honda-nc750x',
            'is_public' => false,
        ]);

        $this->artisan('garagebook:deployment-smoke-test')
            ->expectsOutputToContain('No indexable public garage page found via PublicGarageService::indexableVehicles().')
            ->expectsOutputToContain('FAILED')
            ->assertExitCode(1);
    }

    private function createPublicVehicle(): Vehicle
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'NC750X',
            'year' => 2026,
            'public_slug' => '2026-honda-nc750x',
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud vastgelegd',
            'km_reading' => 1200,
            'maintenance_date' => '2026-06-01',
            'notes' => 'Eerste onderhoudsbeurt met bewijs.',
        ]);

        return $vehicle->refresh();
    }
}
