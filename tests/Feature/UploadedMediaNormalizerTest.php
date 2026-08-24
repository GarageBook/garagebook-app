<?php

namespace Tests\Feature;

use App\Support\ImageUploadSupport;
use App\Support\UploadedMediaNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class UploadedMediaNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_heif_and_heic_mime_types_are_allowed(): void
    {
        $this->assertContains('image/heif', ImageUploadSupport::acceptedImageMimeTypes());
        $this->assertContains('image/heic', ImageUploadSupport::acceptedImageMimeTypes());
        $this->assertContains('image/heif-sequence', ImageUploadSupport::acceptedImageMimeTypes());
        $this->assertContains('image/heic-sequence', ImageUploadSupport::acceptedImageMimeTypes());
        $this->assertContains('image/x-heif', ImageUploadSupport::acceptedImageMimeTypes());
        $this->assertContains('image/x-heic', ImageUploadSupport::acceptedImageMimeTypes());
    }

    public function test_successful_heif_upload_is_converted_to_jpg(): void
    {
        Storage::fake('public');
        $this->putPngImage('vehicle-photos/apple-export.heif');

        $paths = app(UploadedMediaNormalizer::class)->normalizeImageList([
            'vehicle-photos/apple-export.heif',
        ], 'public');

        $this->assertSame(['vehicle-photos/apple-export.jpg'], $paths);
        Storage::disk('public')->assertExists('vehicle-photos/apple-export.jpg');
        Storage::disk('public')->assertMissing('vehicle-photos/apple-export.heif');
    }

    public function test_successful_heic_upload_is_converted_to_jpg(): void
    {
        Storage::fake('public');
        $this->putPngImage('vehicle-photos/iphone-photo.heic');

        $paths = app(UploadedMediaNormalizer::class)->normalizeImageList([
            'vehicle-photos/iphone-photo.heic',
        ], 'public');

        $this->assertSame(['vehicle-photos/iphone-photo.jpg'], $paths);
        Storage::disk('public')->assertExists('vehicle-photos/iphone-photo.jpg');
        Storage::disk('public')->assertMissing('vehicle-photos/iphone-photo.heic');
    }

    public function test_uppercase_heif_and_heic_extensions_are_converted_to_jpg(): void
    {
        Storage::fake('public');
        $this->putPngImage('vehicle-photos/UPPER.HEIF');
        $this->putPngImage('vehicle-photos/OTHER.HEIC');

        $paths = app(UploadedMediaNormalizer::class)->normalizeImageList([
            'vehicle-photos/UPPER.HEIF',
            'vehicle-photos/OTHER.HEIC',
        ], 'public');

        $this->assertSame([
            'vehicle-photos/UPPER.jpg',
            'vehicle-photos/OTHER.jpg',
        ], $paths);
        Storage::disk('public')->assertExists('vehicle-photos/UPPER.jpg');
        Storage::disk('public')->assertExists('vehicle-photos/OTHER.jpg');
        Storage::disk('public')->assertMissing('vehicle-photos/UPPER.HEIF');
        Storage::disk('public')->assertMissing('vehicle-photos/OTHER.HEIC');
    }

    public function test_file_with_only_heic_extension_but_no_valid_image_is_rejected(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicle-photos/not-an-image.HEIC', 'not an image');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HEIF/HEIC-afbeeldingen kunnen op deze server niet worden gelezen.');

        app(UploadedMediaNormalizer::class)->normalizeImageList([
            'vehicle-photos/not-an-image.HEIC',
        ], 'public');
    }

    public function test_existing_jpg_png_and_webp_uploads_still_work(): void
    {
        Storage::fake('public');
        $this->putJpegImage('vehicle-photos/photo.jpg');
        $this->putPngImage('vehicle-photos/photo.png');
        $this->putWebpImage('vehicle-photos/photo.webp');

        $paths = app(UploadedMediaNormalizer::class)->normalizeImageList([
            'vehicle-photos/photo.jpg',
            'vehicle-photos/photo.png',
            'vehicle-photos/photo.webp',
        ], 'public');

        $this->assertSame([
            'vehicle-photos/photo.jpg',
            'vehicle-photos/photo.jpg',
            'vehicle-photos/photo.jpg',
        ], $paths);
        Storage::disk('public')->assertExists('vehicle-photos/photo.jpg');
    }

    private function putPngImage(string $path): void
    {
        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));
        $this->writeImage($path, fn (string $target) => imagepng($image, $target));
        imagedestroy($image);
    }

    private function putJpegImage(string $path): void
    {
        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        $this->writeImage($path, fn (string $target) => imagejpeg($image, $target, 82));
        imagedestroy($image);
    }

    private function putWebpImage(string $path): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP support is not available.');
        }

        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 200, 120));
        $this->writeImage($path, fn (string $target) => imagewebp($image, $target, 82));
        imagedestroy($image);
    }

    private function writeImage(string $path, callable $writer): void
    {
        $fullPath = Storage::disk('public')->path($path);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        $writer($fullPath);
    }
}
