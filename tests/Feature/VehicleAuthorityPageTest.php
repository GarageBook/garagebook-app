<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\Page;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleAuthorityPageTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePublicVehicle(User $user, string $brand, string $model, array $extra = []): Vehicle
    {
        return Vehicle::query()->create(array_merge([
            'user_id' => $user->id,
            'brand' => $brand,
            'model' => $model,
            'year' => 2020,
            'public_slug' => $brand.'-'.$model.'-'.$user->id,
            'is_public' => true,
        ], $extra));
    }

    private function regularUser(): User
    {
        return User::factory()->create(['is_outreach_demo' => false]);
    }

    private function sync(): void
    {
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Route
    // -------------------------------------------------------------------------

    public function test_returns_200_for_existing_model(): void
    {
        $user = $this->regularUser();
        $vehicle = $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 5000,
            'maintenance_date' => '2024-01-15',
        ]);

        $this->sync();

        $this->get('/onderhoud/yamaha-mt-07')->assertOk();
    }

    public function test_returns_404_for_unknown_model(): void
    {
        $this->sync();

        $this->get('/onderhoud/does-not-exist-anywhere')->assertNotFound();
    }

    public function test_returns_404_when_all_vehicles_belong_to_outreach_demo_users(): void
    {
        $demoUser = User::factory()->outreachDemo()->create();
        $this->makePublicVehicle($demoUser, 'Kawasaki', 'Z900', ['public_slug' => 'demo-z900']);

        $this->sync();

        $this->get('/onderhoud/kawasaki-z900')->assertNotFound();
    }

    public function test_returns_404_when_no_vehicles_are_public(): void
    {
        $user = $this->regularUser();
        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CB500F',
            'is_public' => false,
            'public_slug' => null,
        ]);

        $this->sync();

        $this->get('/onderhoud/honda-cb500f')->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Page content
    // -------------------------------------------------------------------------

    public function test_page_contains_h1_with_brand_and_model(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee('Yamaha MT-07 onderhoud bijhouden');
    }

    public function test_page_shows_public_vehicles_of_same_model(): void
    {
        $user = $this->regularUser();
        $vehicle = $this->makePublicVehicle($user, 'Yamaha', 'MT-07', ['public_slug' => 'yamaha-mt07-public']);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Remblokken vervangen',
            'km_reading' => 8000,
            'maintenance_date' => '2024-03-01',
        ]);

        $this->sync();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee('/garage/yamaha-mt07-public', false);
    }

    public function test_page_shows_at_most_five_public_vehicles(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $user = $this->regularUser();
            $this->makePublicVehicle($user, 'Honda', 'CB650R', [
                'public_slug' => 'honda-cb650r-'.$i,
            ]);
        }

        $this->sync();

        $response = $this->get('/onderhoud/honda-cb650r')->assertOk();

        $count = substr_count($response->getContent(), '/garage/honda-cb650r-');
        $this->assertLessThanOrEqual(5, $count, 'More than 5 public vehicles shown');
    }

    public function test_page_shows_related_models_from_same_brand(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07', ['public_slug' => 'yamaha-mt07-a']);
        $this->makePublicVehicle($user, 'Yamaha', 'MT-09', ['public_slug' => 'yamaha-mt09-a']);

        $this->sync();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee('/onderhoud/yamaha-mt-09', false);
    }

    public function test_page_shows_faq_section(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee('Hoe houd ik het onderhoud van een Yamaha MT-07 bij?');
    }

    public function test_page_contains_internal_links_to_related_seo_pages(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();

        $this->assertStringContainsString('/digitaal-onderhoudsboekje', $response->getContent());
        $this->assertStringContainsString('/onderhoudsboekje-kwijt', $response->getContent());
        $this->assertStringContainsString('/onderhoudshistorie-auto', $response->getContent());
        $this->assertStringContainsString('/universeel-onderhoudsboekje', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Canonical URL
    // -------------------------------------------------------------------------

    public function test_page_has_correct_canonical_url(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee(url('/onderhoud/yamaha-mt-07'), false);
    }

    // -------------------------------------------------------------------------
    // Structured data
    // -------------------------------------------------------------------------

    public function test_page_has_webpage_structured_data(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();

        $this->assertStringContainsString('"@type": "WebPage"', $response->getContent());
    }

    public function test_page_has_breadcrumblist_in_structured_data(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();

        $this->assertStringContainsString('"@type": "BreadcrumbList"', $response->getContent());
    }

    public function test_page_has_faqpage_structured_data(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();

        $this->assertStringContainsString('"@type": "FAQPage"', $response->getContent());
    }

    public function test_structured_data_does_not_contain_duplicate_graph_types(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $content = $response->getContent();

        $webPageCount = substr_count($content, '"@type": "WebPage"');
        $faqCount = substr_count($content, '"@type": "FAQPage"');

        $this->assertLessThanOrEqual(1, $webPageCount, 'Duplicate WebPage in structured data');
        $this->assertLessThanOrEqual(1, $faqCount, 'Duplicate FAQPage in structured data');
    }

    // -------------------------------------------------------------------------
    // Sitemap (sitemap-onderhoud.xml — legacy slug-based)
    // -------------------------------------------------------------------------

    public function test_sitemap_onderhoud_returns_xml(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $this->get('/sitemap-onderhoud.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_onderhoud_contains_model_slug(): void
    {
        $user = $this->regularUser();
        $this->makePublicVehicle($user, 'Yamaha', 'MT-07');

        $this->sync();

        $this->get('/sitemap-onderhoud.xml')
            ->assertOk()
            ->assertSee(url('/onderhoud/yamaha-mt-07'), false);
    }

    public function test_sitemap_onderhoud_excludes_outreach_demo_vehicles(): void
    {
        $demoUser = User::factory()->outreachDemo()->create();
        $this->makePublicVehicle($demoUser, 'Suzuki', 'GSX-R750', ['public_slug' => 'demo-gsxr']);

        $this->sync();

        $response = $this->get('/sitemap-onderhoud.xml');
        $this->assertStringNotContainsString(
            url('/onderhoud/suzuki-gsx-r750'),
            $response->getContent()
        );
    }

    // -------------------------------------------------------------------------
    // Route does not conflict with page catch-all
    // -------------------------------------------------------------------------

    public function test_onderhoud_route_does_not_break_page_catch_all(): void
    {
        Page::query()->create([
            'title' => 'Informatie',
            'slug' => 'informatie',
            'content' => 'Test inhoud.',
            'indexable' => true,
        ]);

        $this->get('/informatie')->assertOk()->assertSee('Informatie');
    }
}
