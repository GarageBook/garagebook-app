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
            'indexable' => true,
            'content' => 'Neem contact op.',
        ]);

        Page::query()->create([
            'title' => 'Verborgen pagina',
            'slug' => 'verborgen-pagina',
            'indexable' => false,
            'content' => 'Niet indexeren.',
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
            ->assertDontSee(url('/verborgen-pagina'), false)
            ->assertDontSee(url('/blogs/verborgen-blog'), false);
    }
}
