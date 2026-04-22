<?php

namespace Tests\Unit;

use App\Support\PdfThumbnail;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PdfThumbnailTest extends TestCase
{
    public function test_it_generates_a_small_jpeg_data_uri_for_supported_images(): void
    {
        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);

        $path = $directory . '/pdf-thumbnail-source.png';

        $image = imagecreatetruecolor(1200, 800);
        $background = imagecolorallocate($image, 12, 12, 12);
        $accent = imagecolorallocate($image, 255, 210, 0);

        imagefill($image, 0, 0, $background);
        imagefilledellipse($image, 600, 400, 500, 300, $accent);
        imagepng($image, $path);
        imagedestroy($image);

        $thumbnail = PdfThumbnail::fromPath($path, 240);

        $this->assertNotNull($thumbnail);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $thumbnail);
        $this->assertLessThan(200000, strlen($thumbnail));

        File::delete($path);
    }
}
