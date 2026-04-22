<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogResource as LegacyBlogResource;
use App\Filament\Resources\Blogs\BlogResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Blog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_blogs_are_publicly_visible(): void
    {
        $blog = Blog::query()->create([
            'title' => 'Publieke blog',
            'slug' => 'publieke-blog',
            'excerpt' => 'Publieke excerpt',
            'content' => 'Publieke content',
            'published_at' => now(),
        ]);

        $this->get('/blogs')
            ->assertOk()
            ->assertSee('Publieke blog');

        $this->get('/blogs/' . $blog->slug)
            ->assertOk()
            ->assertSee('Publieke blog')
            ->assertSee('Publieke content');
    }

    public function test_unpublished_blogs_are_not_publicly_visible(): void
    {
        $blog = Blog::query()->create([
            'title' => 'Verborgen blog',
            'slug' => 'verborgen-blog',
            'content' => 'Verborgen content',
            'published_at' => null,
        ]);

        $this->get('/blogs')
            ->assertOk()
            ->assertDontSee('Verborgen blog');

        $this->get('/blogs/' . $blog->slug)
            ->assertNotFound();
    }

    public function test_pages_are_publicly_visible(): void
    {
        $page = Page::query()->create([
            'title' => 'Over ons',
            'slug' => 'over-ons',
            'hero_image' => 'page-images/hero.jpg',
            'content' => 'Publieke pagina-inhoud',
        ]);

        $this->get('/' . $page->slug)
            ->assertOk()
            ->assertSee('Over ons')
            ->assertSee('storage/page-images/hero.jpg')
            ->assertSee('Publieke pagina-inhoud');
    }

    public function test_regular_users_cannot_access_blog_management(): void
    {
        $user = User::factory()->create([
            'email' => 'rider@example.com',
        ]);

        $blog = Blog::query()->create([
            'title' => 'Test blog',
            'slug' => 'test-blog',
            'content' => 'Test content',
        ]);

        $this->actingAs($user);

        $this->assertFalse(BlogResource::canViewAny());
        $this->assertFalse(BlogResource::shouldRegisterNavigation());
        $this->assertFalse(BlogResource::canCreate());
        $this->assertFalse(BlogResource::canEdit($blog));
        $this->assertFalse(BlogResource::canDelete($blog));

        $this->assertFalse(LegacyBlogResource::canViewAny());
        $this->assertFalse(LegacyBlogResource::shouldRegisterNavigation());
        $this->assertFalse(LegacyBlogResource::canCreate());
        $this->assertFalse(LegacyBlogResource::canEdit($blog));
        $this->assertFalse(LegacyBlogResource::canDelete($blog));

        $page = Page::query()->create([
            'title' => 'Test pagina',
            'slug' => 'test-pagina',
            'content' => 'Test pagina-inhoud',
        ]);

        $this->assertFalse(PageResource::canViewAny());
        $this->assertFalse(PageResource::shouldRegisterNavigation());
        $this->assertFalse(PageResource::canCreate());
        $this->assertFalse(PageResource::canEdit($page));
        $this->assertFalse(PageResource::canDelete($page));
    }

    public function test_admin_can_access_blog_management(): void
    {
        $user = User::factory()->create([
            'email' => 'willemvanveelen@icloud.com',
            'is_admin' => true,
        ]);

        $blog = Blog::query()->create([
            'title' => 'Admin blog',
            'slug' => 'admin-blog',
            'content' => 'Admin content',
        ]);

        $this->actingAs($user);

        $this->assertTrue(BlogResource::canViewAny());
        $this->assertTrue(BlogResource::shouldRegisterNavigation());
        $this->assertTrue(BlogResource::canCreate());
        $this->assertTrue(BlogResource::canEdit($blog));
        $this->assertTrue(BlogResource::canDelete($blog));

        $this->assertTrue(LegacyBlogResource::canViewAny());
        $this->assertTrue(LegacyBlogResource::shouldRegisterNavigation());
        $this->assertTrue(LegacyBlogResource::canCreate());
        $this->assertTrue(LegacyBlogResource::canEdit($blog));
        $this->assertTrue(LegacyBlogResource::canDelete($blog));

        $page = Page::query()->create([
            'title' => 'Admin pagina',
            'slug' => 'admin-pagina',
            'content' => 'Admin pagina-inhoud',
        ]);

        $this->assertTrue(PageResource::canViewAny());
        $this->assertTrue(PageResource::shouldRegisterNavigation());
        $this->assertTrue(PageResource::canCreate());
        $this->assertTrue(PageResource::canEdit($page));
        $this->assertTrue(PageResource::canDelete($page));
    }
}
