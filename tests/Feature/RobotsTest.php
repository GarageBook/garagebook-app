<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    public function test_app_robots_txt_only_includes_public_garage_sitemap(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        $this->get('https://app.garagebook.nl/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeaderMissing('Location')
            ->assertSee('User-agent: *', false)
            ->assertSee('Allow: /', false)
            ->assertSee('Sitemap: https://app.garagebook.nl/sitemap-garages.xml', false)
            ->assertDontSee('sitemap.xml', false)
            ->assertDontSee('sitemap-onderhoud.xml', false)
            ->assertDontSee('sitemap-vehicle-authority.xml', false);
    }
}
