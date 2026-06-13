<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSeoPagesAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_SLUGS = [
        'digitaal-onderhoudsboekje',
        'motor-onderhoud-app',
        'motor-onderhoud-bijhouden',
        'onderhoudsboekje-motor',
    ];

    public function test_restored_public_seo_pages_resolve_via_existing_slug_route(): void
    {
        foreach (self::EXPECTED_SLUGS as $slug) {
            $page = Page::query()->where('slug', $slug)->first();

            $this->assertNotNull($page, 'Expected seeded page missing for slug: ' . $slug);

            $this->get('/' . $slug)
                ->assertOk()
                ->assertSeeText($page->title);
        }
    }

    public function test_sitemap_contains_restored_working_seo_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();

        foreach (self::EXPECTED_SLUGS as $slug) {
            $response->assertSee(url('/' . $slug), false);
        }
    }
}
