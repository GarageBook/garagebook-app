<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicResponseCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_uses_cache_headers_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=600', $cacheControl);
        $this->assertStringContainsString('s-maxage=600', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=86400', $cacheControl);
    }

    public function test_public_page_with_query_string_is_not_publicly_cached(): void
    {
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => '<p>Contact</p>',
        ]);

        $response = $this->get('/contact?contact_sent=1');

        $response->assertOk();
        $this->assertStringNotContainsString('public, max-age=600', (string) $response->headers->get('Cache-Control'));
    }
}
