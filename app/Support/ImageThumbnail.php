<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageThumbnail
{
    public static function path(?string $path, int $maxDimension = 320, int $quality = 75): ?string
    {
        if (blank($path) || ! MediaPath::isImage($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $sourcePath = $disk->path($path);

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            return null;
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

        if (! self::canLoadIntoMemory($width, $height)) {
            return null;
        }

        if ($width <= $maxDimension && $height <= $maxDimension) {
            return ltrim($path, '/');
        }

        $signature = md5(implode('|', [
            ltrim($path, '/'),
            (string) @filemtime($sourcePath),
            (string) $maxDimension,
            (string) $quality,
        ]));

        $thumbnailPath = '.thumbnails/' . $signature . '.jpg';

        if ($disk->exists($thumbnailPath)) {
            return $thumbnailPath;
        }

        $source = self::createSourceImage($sourcePath, $mimeType);

        if (! $source) {
            return null;
        }

        [$targetWidth, $targetHeight] = self::targetSize($width, $height, $maxDimension);

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $thumbnail) {
            imagedestroy($source);

            return null;
        }

        $background = imagecolorallocate($thumbnail, 255, 255, 255);
        imagefill($thumbnail, 0, 0, $background);

        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        $directory = Str::beforeLast($thumbnailPath, '/');

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        ob_start();

        $encoded = imagejpeg($thumbnail, null, max(1, min($quality, 100)));
        $contents = ob_get_clean();

        imagedestroy($thumbnail);
        imagedestroy($source);

        if (! $encoded || ! is_string($contents) || $contents === '') {
            return null;
        }

        $disk->put($thumbnailPath, $contents);

        return $thumbnailPath;
    }

    private static function canLoadIntoMemory(int $width, int $height): bool
    {
        $estimatedBytes = $width * $height * 5;

        return $estimatedBytes <= 128 * 1024 * 1024;
    }

    private static function createSourceImage(string $path, string $mimeType): mixed
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            'image/bmp', 'image/x-ms-bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : null,
            default => null,
        };
    }

    private static function targetSize(int $width, int $height, int $maxDimension): array
    {
        if ($width >= $height) {
            $targetWidth = $maxDimension;
            $targetHeight = (int) max(1, round(($height / $width) * $maxDimension));

            return [$targetWidth, $targetHeight];
        }

        $targetHeight = $maxDimension;
        $targetWidth = (int) max(1, round(($width / $height) * $maxDimension));

        return [$targetWidth, $targetHeight];
    }
}
