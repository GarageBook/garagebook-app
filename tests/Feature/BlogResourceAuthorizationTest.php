<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogResource as LegacyBlogResource;
use App\Filament\Resources\Blogs\BlogResource;
use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_admin_can_access_blog_management(): void
    {
        $user = User::factory()->create([
            'email' => 'willemvanveelen@icloud.com',
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
    }
}
