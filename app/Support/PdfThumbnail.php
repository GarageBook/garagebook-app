<?php

namespace App\Support;

class PdfThumbnail
{
    public static function fromPath(string $path, int $maxDimension = 240, int $quality = 75): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $imageInfo = @getimagesize($path);

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

        $source = self::createSourceImage($path, $mimeType);

        if (! $source) {
            return null;
        }

        [$targetWidth, $targetHeight] = self::targetSize($width, $height, $maxDimension);

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $thumbnail) {
            imagedestroy($source);

            return null;
        }

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);

        $background = imagecolorallocatealpha($thumbnail, 255, 255, 255, 0);
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

        $dataUri = self::encodeAsJpegDataUri($thumbnail, $quality);

        imagedestroy($thumbnail);
        imagedestroy($source);

        return $dataUri;
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
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return [$width, $height];
        }

        if ($width >= $height) {
            $targetWidth = $maxDimension;
            $targetHeight = (int) max(1, round(($height / $width) * $maxDimension));

            return [$targetWidth, $targetHeight];
        }

        $targetHeight = $maxDimension;
        $targetWidth = (int) max(1, round(($width / $height) * $maxDimension));

        return [$targetWidth, $targetHeight];
    }

    private static function encodeAsJpegDataUri(mixed $image, int $quality): ?string
    {
        ob_start();

        $encoded = imagejpeg($image, null, max(1, min($quality, 100)));
        $contents = ob_get_clean();

        if (! $encoded || ! is_string($contents) || $contents === '') {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($contents);
    }
}
