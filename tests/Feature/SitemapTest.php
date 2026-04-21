<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_public_pages_and_published_blogs(): void
    {
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'content' => 'Neem contact op.',
        ]);

        Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'content' => 'Publieke inhoud',
            'published_at' => now(),
        ]);

        Blog::query()->create([
            'title' => 'Verborgen blog',
            'slug' => 'verborgen-blog',
            'content' => 'Verborgen inhoud',
            'published_at' => null,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(url('/'), false)
            ->assertSee(url('/blogs'), false)
            ->assertSee(url('/contact'), false)
            ->assertSee(url('/blogs/publieke-blog'), false)
            ->assertDontSee(url('/blogs/verborgen-blog'), false);
    }
}
