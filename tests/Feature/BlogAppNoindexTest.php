<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAppNoindexTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_blog_index_redirects_to_apex_blog_index(): void
    {
        Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'excerpt' => 'Publieke excerpt',
            'content' => 'Publieke content',
            'published_at' => now(),
        ]);

        $this->get('https://app.garagebook.nl/blogs')
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/blogs');
    }

    public function test_app_blog_detail_redirects_to_canonical_blog_detail(): void
    {
        $blog = Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'excerpt' => 'Publieke excerpt',
            'content' => 'Publieke content',
            'published_at' => now(),
        ]);

        $this->get('https://app.garagebook.nl/blogs/'.$blog->slug)
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/blog/'.$blog->slug.'/');
    }

    public function test_garagebook_legacy_blog_detail_redirects_to_canonical_blog_detail(): void
    {
        $blog = Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'excerpt' => 'Publieke excerpt',
            'content' => 'Publieke content',
            'published_at' => now(),
        ]);

        $this->get('https://garagebook.nl/blogs/'.$blog->slug)
            ->assertStatus(301)
            ->assertRedirect('https://garagebook.nl/blog/'.$blog->slug.'/');
    }
}
