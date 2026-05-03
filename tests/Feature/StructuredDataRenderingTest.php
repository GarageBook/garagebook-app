<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->get('/blogs/' . $blog->slug);

        $response->assertOk();
        $response->assertSee('"@context": "https://schema.org"', false);
        $response->assertDontSee('__contextArgs', false);
    }
}
