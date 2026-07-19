<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\MaintenanceLog;
use App\Models\Page;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicSeoCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_host_public_url_redirects_to_apex_and_preserves_querystring(): void
    {
        Page::query()->create([
            'title' => 'Privacy statement',
            'slug' => 'privacy-statement',
            'content' => '<p>Body</p>',
        ]);

        $this->get('https://app.garagebook.nl/privacy-statement?utm_source=gsc&x=1')
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/privacy-statement?utm_source=gsc&x=1');
    }

    public function test_app_host_app_routes_are_not_host_redirected(): void
    {
        $this->get('https://app.garagebook.nl/admin/login')
            ->assertStatus(200);

        $this->get('https://app.garagebook.nl/admin/register')
            ->assertStatus(200);

        $this->get('https://app.garagebook.nl/register')
            ->assertStatus(200);

        $this->get('https://app.garagebook.nl/login')
            ->assertStatus(404)
            ->assertHeaderMissing('Location');

        $this->get('https://app.garagebook.nl/admin/password-reset/request')
            ->assertStatus(200);

        $this->get('https://app.garagebook.nl/admin/password-reset/reset')
            ->assertStatus(403)
            ->assertHeaderMissing('Location');

        $this->post('https://app.garagebook.nl/admin/logout')
            ->assertRedirect('https://app.garagebook.nl/admin/login');

        $this->get('https://app.garagebook.nl/api/ping')
            ->assertStatus(404);

        $this->post('https://app.garagebook.nl/livewire-33d43eea/update')
            ->assertStatus(404)
            ->assertHeaderMissing('Location');

        $this->get('https://app.garagebook.nl/livewire-33d43eea/livewire.js')
            ->assertStatus(200);
    }

    public function test_unknown_app_host_path_is_not_redirected_to_public_host(): void
    {
        $this->get('https://app.garagebook.nl/future-app-entrypoint')
            ->assertStatus(404)
            ->assertHeaderMissing('Location');
    }

    public function test_start_redirects_to_app_register_for_get_and_head(): void
    {
        $this->get('https://app.garagebook.nl/start')
            ->assertStatus(302)
            ->assertRedirect('https://app.garagebook.nl/admin/register');

        $this->head('https://app.garagebook.nl/start')
            ->assertStatus(302)
            ->assertRedirect('https://app.garagebook.nl/admin/register');
    }

    public function test_start_redirect_preserves_raw_tracking_querystring_exactly(): void
    {
        $queryString = implode('&', [
            'utm_source=garagebook.nl',
            'utm_medium=website',
            'utm_campaign=organic_cta',
            'utm_content=hero',
            'utm_term=motor%20onderhoud',
            'utm_id=launch-2026',
            'gclid=test-gclid',
            'dclid=test-dclid',
            'gbraid=test-gbraid',
            'wbraid=test-wbraid',
            'gad_source=1',
            '_ga=test-ga',
            '_gid=test-gid',
            '_gac=test-gac',
            '_gl=1%2Atest-value%2Atest-extra',
            'fbclid=test-fbclid',
            'msclkid=test-msclkid',
            'ttclid=test-ttclid',
            'custom_future_parameter=behouden',
            'empty=',
            'duplicate=first',
            'duplicate=second',
            'encoded=a%2Bb%3Dc%2520d',
        ]);

        $this->get('https://app.garagebook.nl/start?'.$queryString)
            ->assertStatus(302)
            ->assertRedirect('https://app.garagebook.nl/admin/register?'.$queryString)
            ->assertHeader('Location', 'https://app.garagebook.nl/admin/register?'.$queryString);
    }

    public function test_start_redirect_follow_ends_on_app_register_page_with_form(): void
    {
        $queryString = '_gl=1%2Atest-value%2Atest-extra&gclid=test-gclid&custom_future_parameter=behouden';
        $firstResponse = $this->get('https://app.garagebook.nl/start?'.$queryString);

        $firstResponse
            ->assertStatus(302)
            ->assertRedirect('https://app.garagebook.nl/admin/register?'.$queryString);

        $location = $firstResponse->headers->get('Location');
        $this->assertSame('app.garagebook.nl', parse_url($location, PHP_URL_HOST));

        $this->get($location)
            ->assertOk()
            ->assertSee('GarageBook')
            ->assertSee('Naam')
            ->assertSee('E-mailadres')
            ->assertSee('Wachtwoord');
    }

    public function test_blog_detail_legacy_urls_redirect_to_single_canonical_url(): void
    {
        $blog = $this->publishedBlog();

        $this->get('https://garagebook.nl/blog/'.$blog->slug)
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/blog/'.$blog->slug.'/');

        $this->get('https://app.garagebook.nl/blogs/'.$blog->slug)
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/blog/'.$blog->slug.'/');
    }

    public function test_blog_detail_canonical_and_internal_links_use_canonical_route(): void
    {
        $blog = $this->publishedBlog('primaire-blog');
        $related = $this->publishedBlog('gerelateerde-blog');

        $response = $this->kernelGet('https://garagebook.nl/blog/'.$blog->slug.'/');

        $response->assertOk()
            ->assertSee('<link rel="canonical" href="https://garagebook.nl/blog/'.$blog->slug.'/">', false)
            ->assertSee('"url": "https://garagebook.nl/blog/'.$blog->slug.'/"', false)
            ->assertDontSee('/blogs/'.$blog->slug, false);

        $this->get('https://garagebook.nl/blogs')
            ->assertOk()
            ->assertSee('https://garagebook.nl/blog/'.$blog->slug.'/', false)
            ->assertSee('https://garagebook.nl/blog/'.$related->slug.'/', false)
            ->assertDontSee('/blogs/'.$blog->slug, false);
    }

    public function test_public_garage_canonical_and_sitemap_use_apex_host(): void
    {
        $vehicle = $this->publicVehicle();

        $this->get('/garage/'.$vehicle->public_slug)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://garagebook.nl/garage/'.$vehicle->public_slug.'">', false);

        $this->get('/sitemap-garages.xml')
            ->assertOk()
            ->assertSee('https://garagebook.nl/garage/'.$vehicle->public_slug, false)
            ->assertDontSee('app.garagebook.nl', false);
    }

    public function test_outreach_demo_vehicle_stays_noindex_and_out_of_sitemap(): void
    {
        $demoUser = User::factory()->outreachDemo()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $demoUser->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'display_variant' => 'Garage demo',
            'year' => 2023,
            'public_slug' => '2023-yamaha-mt-07-garage-demo-26',
            'is_public' => true,
        ]);

        $this->get('/garage/'.$vehicle->public_slug)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="https://garagebook.nl/garage/'.$vehicle->public_slug.'">', false);

        $this->get('/sitemap-garages.xml')
            ->assertOk()
            ->assertDontSee($vehicle->public_slug, false);
    }

    public function test_valid_legacy_share_redirects_and_unknown_legacy_share_returns_gone(): void
    {
        Log::spy();

        $vehicle = $this->publicVehicle(ownerName: 'Willem van Veelen', attributes: [
            'brand' => 'Honda',
            'model' => 'CBR600RR',
            'year' => 2005,
        ]);

        $this->get('/share/willem-van-veelen/honda-cbr600rr')
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/garage/'.$vehicle->public_slug);

        $this->get('/share/bauke-huitema/project-13')
            ->assertStatus(410);

        Log::shouldHaveReceived('warning')
            ->with('unknown_legacy_public_garage_share_url', \Mockery::on(
                fn (array $context): bool => ($context['username'] ?? null) === 'bauke-huitema'
                    && ($context['vehicle_slug'] ?? null) === 'project-13'
            ));
    }

    public function test_index_html_redirects_to_slash_url_without_touching_protected_assets(): void
    {
        $this->get('/youngtimer-onderhoud-bijhouden/index.html')
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/youngtimer-onderhoud-bijhouden/');

        $this->get('https://app.garagebook.nl/unknown-app-entrypoint/index.html')
            ->assertStatus(404);

        $this->get('/build/index.html')
            ->assertStatus(404);

        $this->get('/storage/index.html')
            ->assertStatus(404);
    }

    public function test_sitemap_contains_only_canonical_indexable_urls(): void
    {
        Page::query()->create([
            'title' => 'Youngtimer onderhoud bijhouden',
            'slug' => 'youngtimer-onderhoud-bijhouden',
            'content' => '<p>Body</p>',
            'indexable' => true,
        ]);
        Page::query()->create([
            'title' => 'Privacy',
            'slug' => 'privacy-statement',
            'content' => '<p>Body</p>',
            'indexable' => false,
        ]);
        $blog = $this->publishedBlog();
        $vehicle = $this->publicVehicle();

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $garageSitemap = $this->get('/sitemap-garages.xml')->assertOk()->getContent();

        $this->assertStringContainsString('https://garagebook.nl/youngtimer-onderhoud-bijhouden', $sitemap);
        $this->assertStringContainsString('https://garagebook.nl/blog/'.$blog->slug.'/', $sitemap);
        $this->assertStringContainsString('https://garagebook.nl/garage/'.$vehicle->public_slug, $garageSitemap);
        $this->assertStringNotContainsString('app.garagebook.nl', $sitemap.$garageSitemap);
        $this->assertStringNotContainsString('/index.html', $sitemap.$garageSitemap);
        $this->assertStringNotContainsString('/share/', $sitemap.$garageSitemap);
        $this->assertStringNotContainsString('privacy-statement', $sitemap);
    }

    public function test_legacy_urls_resolve_without_redirect_chains(): void
    {
        Page::query()->create([
            'title' => 'Youngtimer onderhoud bijhouden',
            'slug' => 'youngtimer-onderhoud-bijhouden',
            'content' => '<p>Body</p>',
            'indexable' => true,
        ]);
        $blog = $this->publishedBlog();
        $vehicle = $this->publicVehicle();

        foreach ([
            'https://app.garagebook.nl/blogs/'.$blog->slug => 'https://garagebook.nl/blog/'.$blog->slug.'/',
            'https://garagebook.nl/blog/'.$blog->slug => 'https://garagebook.nl/blog/'.$blog->slug.'/',
            'https://app.garagebook.nl/garage/'.$vehicle->public_slug => 'https://garagebook.nl/garage/'.$vehicle->public_slug,
            'https://garagebook.nl/youngtimer-onderhoud-bijhouden/index.html' => 'https://garagebook.nl/youngtimer-onderhoud-bijhouden/',
        ] as $source => $target) {
            $response = $this->get($source);
            $response->assertStatus(301)->assertRedirect($target);
            $this->assertSame(200, $this->kernelGet($target)->getStatusCode());
        }
    }

    private function kernelGet(string $url): TestResponse
    {
        return TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create($url, 'GET')));
    }

    private function publishedBlog(string $slug = 'publieke-blog'): Blog
    {
        return Blog::query()->create([
            'title' => str($slug)->replace('-', ' ')->title(),
            'slug' => $slug,
            'excerpt' => 'Korte samenvatting.',
            'content' => '<p>Blog inhoud.</p>',
            'published_at' => now(),
        ]);
    }

    private function publicVehicle(string $ownerName = 'Eigenaar', array $attributes = []): Vehicle
    {
        $user = User::factory()->create(['name' => $ownerName]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander',
            'year' => 2008,
            'is_public' => true,
            ...$attributes,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud vastgelegd',
            'km_reading' => 12345,
            'maintenance_date' => '2026-05-01',
        ]);

        return $vehicle->refresh();
    }
}
