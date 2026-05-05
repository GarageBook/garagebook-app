<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_public_pages_and_excludes_blog_detail_pages(): void
    {
        Page::query()->create([
            'title' => 'Contact',
            'slug' => 'contact',
            'indexable' => true,
            'content' => 'Neem contact op.',
        ]);

        Page::query()->create([
            'title' => 'Ons verhaal',
            'slug' => 'ons-verhaal',
            'indexable' => true,
            'content' => 'Ons verhaal.',
        ]);

        Page::query()->create([
            'title' => 'Verborgen pagina',
            'slug' => 'verborgen-pagina',
            'indexable' => false,
            'content' => 'Niet indexeren.',
        ]);

        Page::query()->create([
            'title' => 'Featured pagina',
            'slug' => 'universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026',
            'indexable' => true,
            'content' => 'Niet in sitemap tonen.',
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
            ->assertSee(url('/ons-verhaal'), false)
            ->assertDontSee(url('/blogs/publieke-blog'), false)
            ->assertDontSee(url('/universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026'), false)
            ->assertDontSee(url('/verborgen-pagina'), false)
            ->assertDontSee(url('/blogs/verborgen-blog'), false);
    }
}
