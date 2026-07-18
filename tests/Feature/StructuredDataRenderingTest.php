<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StructuredDataRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_valid_schema_context(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('"@context": "https://schema.org"', false);
        $response->assertDontSee('__contextArgs', false);
    }

    public function test_blog_page_renders_valid_schema_context(): void
    {
        $blog = Blog::query()->create([
            'title' => 'Test blog',
            'slug' => 'test-blog',
            'excerpt' => 'Korte samenvatting.',
            'content' => '<p>Blog inhoud.</p>',
            'published_at' => now(),
        ]);

        $response = TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create('/blog/'.$blog->slug.'/', 'GET')));
        $canonicalUrl = 'https://garagebook.nl/blog/'.$blog->slug.'/';

        $response->assertOk();
        $response->assertSee('"@context": "https://schema.org"', false);
        $response->assertSee('<link rel="canonical" href="'.$canonicalUrl.'">', false);
        $response->assertSee('<meta property="og:url" content="'.$canonicalUrl.'">', false);
        $response->assertSee('<meta name="twitter:url" content="'.$canonicalUrl.'">', false);
        $response->assertDontSee('<meta name="robots" content="noindex"', false);
        $response->assertSee('"url": "'.$canonicalUrl.'"', false);
        $response->assertSee('"mainEntityOfPage": "'.$canonicalUrl.'"', false);
        $response->assertDontSee('__contextArgs', false);
    }
}
