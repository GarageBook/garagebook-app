<?php

namespace Tests\Feature;

use Tests\TestCase;

class RobotsTest extends TestCase
{
    public function test_robots_txt_includes_sitemap_reference(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *', false)
            ->assertSee('Allow: /', false)
            ->assertSee('Sitemap: ' . url('/sitemap.xml'), false);
    }
}
