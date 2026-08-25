<?php

namespace Tests\Feature;

use App\Support\ImageUploadSupport;
use App\Support\RasterImageProcessor;
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

    public function test_fixture_is_a_real_content_detected_heic_file(): void
    {
        $fixture = $this->heicFixturePath();

        $this->assertFileExists($fixture);
        $this->assertTrue(ImageUploadSupport::isHeifFile($fixture));
    }

    public function test_real_heic_upload_is_converted_to_real_jpg_when_runtime_supports_heic(): void
    {
        $this->skipIfHeicRuntimeIsUnavailable();

        Storage::fake('public');
        Storage::disk('public')->put('vehicle-photos/iphone-photo.HEIC', file_get_contents($this->heicFixturePath()));

        $paths = app(UploadedMediaNormalizer::class)->normalizeImageList([
            'vehicle-photos/iphone-photo.HEIC',
        ], 'public');

        $this->assertCount(1, $paths);
        $this->assertSame('vehicle-photos/iphone-photo.jpg', $paths[0]);
        Storage::disk('public')->assertExists('vehicle-photos/iphone-photo.jpg');
        Storage::disk('public')->assertMissing('vehicle-photos/iphone-photo.HEIC');
        $this->assertTrue(app(RasterImageProcessor::class)->isJpegFile(Storage::disk('public')->path('vehicle-photos/iphone-photo.jpg')));
    }

    public function test_png_content_with_heic_extension_is_rejected_and_not_converted(): void
    {
        Storage::fake('public');
        $this->putPngImage('vehicle-photos/not-real.HEIC');

        try {
            app(UploadedMediaNormalizer::class)->normalizeImageList([
                'vehicle-photos/not-real.HEIC',
            ], 'public');
            $this->fail('Fake HEIC upload was not rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('geen geldige Apple HEIC', $exception->getMessage());
        }

        Storage::disk('public')->assertExists('vehicle-photos/not-real.HEIC');
        Storage::disk('public')->assertMissing('vehicle-photos/not-real.jpg');
    }

    public function test_corrupt_content_detected_heic_is_rejected_when_runtime_supports_heic(): void
    {
        $this->skipIfHeicRuntimeIsUnavailable();

        Storage::fake('public');
        Storage::disk('public')->put('vehicle-photos/corrupt.HEIC', $this->corruptHeicBytes());

        $this->assertTrue(ImageUploadSupport::isHeifFile(Storage::disk('public')->path('vehicle-photos/corrupt.HEIC')));

        $this->expectException(RuntimeException::class);

        app(UploadedMediaNormalizer::class)->normalizeImageList([
            'vehicle-photos/corrupt.HEIC',
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
        $this->assertTrue(app(RasterImageProcessor::class)->isJpegFile(Storage::disk('public')->path('vehicle-photos/photo.jpg')));
    }

    private function skipIfHeicRuntimeIsUnavailable(): void
    {
        try {
            app(RasterImageProcessor::class)->ensureHeifRuntimeIsAvailable();
        } catch (RuntimeException $exception) {
            $this->markTestSkipped($exception->getMessage());
        }
    }

    private function heicFixturePath(): string
    {
        return base_path('tests/Fixtures/images/iphone-sample.heic');
    }

    private function corruptHeicBytes(): string
    {
        return pack('N', 28).'ftypheic'.pack('N', 0).'heicmif1'.'not-enough-image-data';
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
