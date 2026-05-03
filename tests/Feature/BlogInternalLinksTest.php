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
            'title' => 'Digitaal onderhoudsboekje voor je motor',
            'slug' => 'digitaal-onderhoudsboekje-voor-je-motor-wat-is-het-en-hoe-werkt-het',
            'excerpt' => 'Eerste',
            'content' => '<p>Body</p>',
            'published_at' => now()->subDays(2),
        ]);

        $relatedBlog = Blog::query()->create([
            'title' => 'Universeel onderhoudsboekje',
            'slug' => 'waarom-een-universeel-onderhoudsboekje-achterhaald-is-en-wat-je-beter-kunt-gebruiken',
            'excerpt' => 'Tweede',
            'content' => '<p>Body</p>',
            'published_at' => now()->subDay(),
        ]);

        $secondaryRelatedBlog = Blog::query()->create([
            'title' => 'Onderhoudshistorie en verkoopwaarde',
            'slug' => 'hoe-een-complete-onderhoudshistorie-de-verkoopwaarde-van-je-motor-verhoogt',
            'excerpt' => 'Derde',
            'content' => '<p>Body</p>',
            'published_at' => now(),
        ]);

        Blog::query()->create([
            'title' => 'Niet relevant maar nieuwer',
            'slug' => 'niet-relevant-maar-nieuwer',
            'excerpt' => 'Vierde',
            'content' => '<p>Body</p>',
            'published_at' => now()->addMinute(),
        ]);

        $response = $this->get('/blogs/' . $primaryBlog->slug);

        $response->assertOk();
        $response->assertSee('/blogs/' . $relatedBlog->slug, false);
        $response->assertSee('/blogs/' . $secondaryRelatedBlog->slug, false);
        $response->assertSee('/' . $featuredPage->slug, false);
        $response->assertDontSee('/blogs/niet-relevant-maar-nieuwer', false);
    }

    public function test_featured_page_shows_related_blog_links(): void
    {
        $page = Page::query()->create([
            'title' => 'Universeel onderhoudsboekje kopen',
            'slug' => 'universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026',
            'content' => '<p>Body</p>',
        ]);

        $blog = Blog::query()->create([
            'title' => 'Universeel onderhoudsboekje',
            'slug' => 'waarom-een-universeel-onderhoudsboekje-achterhaald-is-en-wat-je-beter-kunt-gebruiken',
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'published_at' => now(),
        ]);

        Blog::query()->create([
            'title' => 'Niet geselecteerde blog',
            'slug' => 'niet-geselecteerde-blog',
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'published_at' => now()->addMinute(),
        ]);

        $response = $this->get('/' . $page->slug);

        $response->assertOk();
        $response->assertSee('/blogs/' . $blog->slug, false);
        $response->assertDontSee('/blogs/niet-geselecteerde-blog', false);
    }
}
