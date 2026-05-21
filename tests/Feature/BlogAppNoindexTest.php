<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAppNoindexTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_blog_index_renders_noindex_follow(): void
    {
        Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'excerpt' => 'Publieke excerpt',
            'content' => 'Publieke content',
            'published_at' => now(),
        ]);

        $this->get('https://app.garagebook.nl/blogs')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="https://garagebook.nl/blogs">', false);
    }

    public function test_app_blog_detail_renders_noindex_follow_and_garagebook_canonical(): void
    {
        $blog = Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'excerpt' => 'Publieke excerpt',
            'content' => 'Publieke content',
            'published_at' => now(),
        ]);

        $this->get('https://app.garagebook.nl/blogs/' . $blog->slug)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="https://garagebook.nl/blog/' . $blog->slug . '/">', false);
    }

    public function test_garagebook_blog_detail_does_not_render_noindex(): void
    {
        $blog = Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'excerpt' => 'Publieke excerpt',
            'content' => 'Publieke content',
            'published_at' => now(),
        ]);

        $this->get('https://garagebook.nl/blogs/' . $blog->slug)
            ->assertOk()
            ->assertDontSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false);
    }
}
