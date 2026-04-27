<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSeoRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_uses_explicit_seo_fields_in_head(): void
    {
        $page = Page::query()->create([
            'title' => 'Over GarageBook',
            'slug' => 'over-garagebook',
            'content' => '<p>Body</p>',
            'meta_title' => 'Over GarageBook',
            'meta_description' => 'Lees meer over GarageBook en hoe het motoronderhoud overzichtelijk maakt.',
            'canonical_url' => 'https://app.garagebook.nl/over-garagebook',
            'indexable' => true,
        ]);

        $response = $this->get('/' . $page->slug);

        $response->assertOk();
        $response->assertSee('<meta name="description" content="Lees meer over GarageBook en hoe het motoronderhoud overzichtelijk maakt.">', false);
        $response->assertSee('<meta name="robots" content="index,follow">', false);
        $response->assertSee('<link rel="canonical" href="https://app.garagebook.nl/over-garagebook">', false);
    }

    public function test_non_indexable_page_renders_noindex_meta_tag(): void
    {
        $page = Page::query()->create([
            'title' => 'Verborgen pagina',
            'slug' => 'verborgen-pagina',
            'content' => '<p>Body</p>',
            'indexable' => false,
        ]);

        $response = $this->get('/' . $page->slug);

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }
}
