<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogInternalLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_detail_shows_related_blogs_and_featured_page_link(): void
    {
        $featuredPage = Page::query()->create([
            'title' => 'Universeel onderhoudsboekje kopen',
            'slug' => 'universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026',
            'content' => '<p>Body</p>',
        ]);

        $primaryBlog = Blog::query()->create([
            'title' => 'Eerste blog',
            'slug' => 'eerste-blog',
            'excerpt' => 'Eerste',
            'content' => '<p>Body</p>',
            'published_at' => now()->subDay(),
        ]);

        $relatedBlog = Blog::query()->create([
            'title' => 'Tweede blog',
            'slug' => 'tweede-blog',
            'excerpt' => 'Tweede',
            'content' => '<p>Body</p>',
            'published_at' => now(),
        ]);

        $response = $this->get('/blogs/' . $primaryBlog->slug);

        $response->assertOk();
        $response->assertSee('/blogs/' . $relatedBlog->slug, false);
        $response->assertSee('/' . $featuredPage->slug, false);
    }

    public function test_featured_page_shows_related_blog_links(): void
    {
        $page = Page::query()->create([
            'title' => 'Universeel onderhoudsboekje kopen',
            'slug' => 'universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026',
            'content' => '<p>Body</p>',
        ]);

        $blog = Blog::query()->create([
            'title' => 'Onderhoud slim bijhouden',
            'slug' => 'onderhoud-slim-bijhouden',
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'published_at' => now(),
        ]);

        $response = $this->get('/' . $page->slug);

        $response->assertOk();
        $response->assertSee('/blogs/' . $blog->slug, false);
    }
}
