<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RasterImageProcessor
{
    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    public function optimizePaths(string $diskName, array $paths, int $maxDimension = 2200, int $quality = 82): array
    {
        $optimized = [];

        foreach ($paths as $path) {
            $optimized[] = $this->optimizeImage($diskName, $path, $maxDimension, $quality) ?? $path;
        }

        return $optimized;
    }

    public function optimizeImage(string $diskName, string $path, int $maxDimension = 2200, int $quality = 82): ?string
    {
        if (! MediaPath::isImage($path) || Str::endsWith(Str::lower($path), '.svg')) {
            return null;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return null;
        }

        $sourcePath = $disk->path($path);

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return null;
        }

        $image = $this->loadImage($sourcePath, $path);

        if ($image === null) {
            return null;
        }

        [$source, $width, $height] = $image;

        if (! $this->canLoadIntoMemory($width, $height)) {
            imagedestroy($source);

            return null;
        }

        [$targetWidth, $targetHeight] = $this->targetSize($width, $height, $maxDimension);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas) {
            imagedestroy($source);

            return null;
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $encoded = imagejpeg($canvas, null, max(1, min($quality, 100)));
        $contents = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if (! $encoded || ! is_string($contents) || $contents === '') {
            return null;
        }

        $targetPath = $this->targetPath($path);
        $originalBytes = @filesize($sourcePath) ?: 0;
        $optimizedBytes = strlen($contents);
        $dimensionsChanged = $targetWidth !== $width || $targetHeight !== $height;
        $extensionChanged = $targetPath !== $path;

        if (! $extensionChanged && ! $dimensionsChanged && $optimizedBytes >= $originalBytes) {
            return null;
        }

        $disk->put($targetPath, $contents);

        if ($extensionChanged && $disk->exists($path)) {
            $disk->delete($path);
        }

        return $targetPath;
    }

    public function validateDecodable(string $diskName, string $path): void
    {
        if (! MediaPath::isImage($path) || Str::endsWith(Str::lower($path), '.svg')) {
            return;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            throw new RuntimeException('Het geuploade afbeeldingsbestand kon niet worden gevonden.');
        }

        $sourcePath = $disk->path($path);
        $image = $this->loadImage($sourcePath, $path);

        if ($image === null) {
            throw new RuntimeException('De afbeelding kon niet worden gelezen. Upload een geldige JPG, PNG, WebP, GIF, BMP, HEIF of HEIC.');
        }

        imagedestroy($image[0]);
    }

    private function loadImage(string $sourcePath, string $storagePath): ?array
    {
        if (ImageUploadSupport::isHeifPath($storagePath)) {
            $heifImage = $this->loadWithImagick($sourcePath);

            if ($heifImage !== null) {
                return $heifImage;
            }
        }

        $imageInfo = @getimagesize($sourcePath);

        if (! is_array($imageInfo)) {
            return null;
        }

        [$width, $height] = $imageInfo;
        $mimeType = $imageInfo['mime'] ?? null;

        if (! $mimeType || $width < 1 || $height < 1) {
            return null;
        }

        if (ImageUploadSupport::isHeifMimeType($mimeType)) {
            return $this->loadWithImagick($sourcePath);
        }

        $source = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            'image/bmp', 'image/x-ms-bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : null,
            default => null,
        };

        return $source ? [$source, (int) $width, (int) $height] : null;
    }

    private function loadWithImagick(string $sourcePath): ?array
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return null;
        }

        $supportedFormats = array_map('strtoupper', \Imagick::queryFormats());

        if (! in_array('HEIC', $supportedFormats, true) && ! in_array('HEIF', $supportedFormats, true)) {
            return null;
        }

        try {
            $imagick = new \Imagick($sourcePath);
            $imagick = $imagick->coalesceImages();
            $imagick->setIteratorIndex(0);
            $imagick->autoOrient();
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $imagick->setImageBackgroundColor('white');
            $imagick->setImageFormat('png');
            $blob = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            $source = @imagecreatefromstring($blob);

            if (! $source) {
                return null;
            }

            return [$source, imagesx($source), imagesy($source)];
        } catch (\Throwable) {
            return null;
        }
    }

    private function canLoadIntoMemory(int $width, int $height): bool
    {
        return ($width * $height * 5) <= 128 * 1024 * 1024;
    }

    private function targetSize(int $width, int $height, int $maxDimension): array
    {
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return [$width, $height];
        }

        if ($width >= $height) {
            return [$maxDimension, (int) max(1, round(($height / $width) * $maxDimension))];
        }

        return [(int) max(1, round(($width / $height) * $maxDimension)), $maxDimension];
    }

    private function targetPath(string $path): string
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return $path;
        }

        return Str::beforeLast($path, '.').'.jpg';
    }
}
