<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BlogImageResponseTest extends TestCase
{
    private string $imageDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageDirectory = storage_path('app/public/blog-images');
        File::ensureDirectoryExists($this->imageDirectory);

        $image = imagecreatetruecolor(12, 12);
        $background = imagecolorallocate($image, 255, 210, 0);
        imagefill($image, 0, 0, $background);
        imagepng($image, $this->imageDirectory . '/test-cache-image.png');
        imagedestroy($image);
    }

    protected function tearDown(): void
    {
        File::delete($this->imageDirectory . '/test-cache-image.png');

        parent::tearDown();
    }

    public function test_blog_image_serves_webp_variant_when_supported(): void
    {
        $this->withHeaders([
            'Accept' => 'image/webp,image/*;q=0.8',
        ])->get('/blog-image/blog-images/test-cache-image.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Vary', 'Accept');
    }

    public function test_blog_image_has_strong_cache_headers(): void
    {
        $response = $this->get('/blog-image/blog-images/test-cache-image.png');

        $response->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=604800', $cacheControl);
        $this->assertStringContainsString('s-maxage=604800', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=86400', $cacheControl);
    }
}
