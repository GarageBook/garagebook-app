<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SeoHealthDashboardController;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeoHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_health_dashboard_route_uses_controller_action(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'admin/seo-health-dashboard')
            ->values();

        $this->assertCount(1, $routes);
        $this->assertSame(SeoHealthDashboardController::class, $routes[0]->getActionName());
    }

    public function test_admin_can_view_seo_health_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/seo-health-dashboard');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
        $response->assertViewIs('admin.seo-health-dashboard');
        $response->assertSee('SEO Health');
        $response->assertSeeText('Indexability');
        $response->assertSeeText('Product coverage');
        $response->assertDontSee('Honda C50');
        $response->assertDontSee('/garage/honda-c50');
    }

    public function test_seo_health_dashboard_can_be_loaded_repeatedly(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withoutExceptionHandling();

        foreach (range(1, 3) as $attempt) {
            Artisan::call('view:clear');

            $response = $this->actingAs($admin)
                ->get('/admin/seo-health-dashboard');

            $response->assertOk();
            $response->assertHeaderMissing('Location');
            $response->assertViewIs('admin.seo-health-dashboard');
            $response->assertSee('SEO Health');
            $response->assertDontSee('Honda C50');
            $response->assertDontSee('/garage/honda-c50');
        }
    }

    public function test_admin_navigation_contains_one_seo_health_item(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $navigationItems = collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->filter(fn ($item) => $item->getLabel() === 'SEO Health')
            ->values();

        $this->assertCount(1, $navigationItems);
        $this->assertSame('/admin/seo-health-dashboard', $navigationItems[0]->getUrl());
        $this->assertSame('Beheer', $navigationItems[0]->getGroup());
        $this->assertSame(192, $navigationItems[0]->getSort());

        $response = $this->get('/admin');

        $response->assertOk();

        $links = $this->seoHealthLinks($response->getContent());

        $this->assertCount(1, $links);
        $this->assertSame('/admin/seo-health-dashboard', $links[0]['href']);
        $this->assertStringContainsString('/admin/seo-health-dashboard', $links[0]['html']);
        $this->assertStringNotContainsString('wire:navigate', $links[0]['html']);
    }

    public function test_regular_user_cannot_view_seo_health_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/seo-health-dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_download_seo_health_dashboard_csv(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/seo-health-export')
            ->assertOk()
            ->assertDownload('seo-health-dashboard-'.now()->format('Y-m-d').'.csv');
    }

    public function test_seo_health_dashboard_and_csv_do_not_expose_owner_identity(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create([
            'name' => 'Sensitive Owner',
            'email' => 'sensitive-owner@example.com',
        ]);
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Honda, Garage',
            'model' => 'NC750X',
            'public_slug' => 'honda-nc750x-sensitive',
            'is_public' => true,
        ]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie',
            'km_reading' => 12_000,
            'maintenance_date' => '2026-07-25',
        ]);

        $dashboard = $this->actingAs($admin)->get('/admin/seo-health-dashboard')->assertOk();
        $dashboard->assertSee('https://garagebook.nl/garage/honda-nc750x-sensitive', false);
        $dashboard->assertDontSee('https://app.garagebook.nl/garage/honda-nc750x-sensitive', false);
        $dashboard->assertDontSee('sensitive-owner@example.com');
        $dashboard->assertDontSee('Sensitive Owner');

        $csv = $this->actingAs($admin)->get('/admin/seo-health-export')->assertOk()->streamedContent();

        $this->assertStringContainsString('https://garagebook.nl/garage/honda-nc750x-sensitive', $csv);
        $this->assertStringNotContainsString('https://app.garagebook.nl/garage/', $csv);
        $this->assertStringNotContainsString('sensitive-owner@example.com', $csv);
        $this->assertStringNotContainsString('Sensitive Owner', $csv);
        $this->assertDoesNotMatchRegularExpression('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $csv);
        $this->assertStringContainsString('missing_photo', $csv);
        $this->assertStringContainsString('short_log_descriptions', $csv);
        $this->assertStringContainsString('"Honda, Garage NC750X"', $csv);
    }

    public function test_regular_user_cannot_download_seo_health_dashboard_csv(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/seo-health-export')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login_for_seo_health_dashboard_csv(): void
    {
        $this->get('/admin/seo-health-export')
            ->assertRedirect('/admin/login');
    }

    public function test_seo_health_dashboard_csv_has_download_headers_and_expected_columns(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/seo-health-export')
            ->assertOk();

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename=seo-health-dashboard-'.now()->format('Y-m-d').'.csv'
        );

        $csv = $response->streamedContent();

        $this->assertStringContainsString('section,metric,value,details,public_url,admin_url,vehicle_id,quality_score,severity,reason_codes', $csv);
        $this->assertStringContainsString('status,"SEO Health status"', $csv);
        $this->assertStringContainsString('product_metrics,"Total Vehicles"', $csv);
    }

    /**
     * @return list<array{href:string,html:string}>
     */
    private function seoHealthLinks(string $html): array
    {
        $document = new \DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $links = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            if (trim(preg_replace('/\s+/', ' ', $anchor->textContent)) !== 'SEO Health') {
                continue;
            }

            $links[] = [
                'href' => $anchor->getAttribute('href'),
                'html' => $document->saveHTML($anchor),
            ];
        }

        return $links;
    }
}
