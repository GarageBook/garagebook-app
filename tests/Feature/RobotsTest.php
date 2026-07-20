<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    public function test_robots_txt_includes_sitemap_reference(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *', false)
            ->assertSee('Allow: /', false)
            ->assertSee('Sitemap: https://garagebook.nl/sitemap.xml', false)
            ->assertSee('Sitemap: https://app.garagebook.nl/sitemap-garages.xml', false)
            ->assertSee('Sitemap: https://garagebook.nl/sitemap-vehicle-authority.xml', false);
    }
}
