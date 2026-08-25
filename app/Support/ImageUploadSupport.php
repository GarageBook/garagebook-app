<?php

namespace App\Support;

class ImageUploadSupport
{
    public const WEB_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp',
        'image/svg+xml',
    ];

    public const HEIF_MIME_TYPES = [
        'image/heif',
        'image/heic',
        'image/heif-sequence',
        'image/heic-sequence',
        'image/x-heif',
        'image/x-heic',
    ];

    public const HEIF_EXTENSIONS = [
        'heif',
        'heic',
    ];

    public static function acceptedImageMimeTypes(): array
    {
        return [
            ...self::WEB_IMAGE_MIME_TYPES,
            ...self::HEIF_MIME_TYPES,
        ];
    }

    public static function isHeifPath(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        return in_array(strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)), self::HEIF_EXTENSIONS, true);
    }

    public static function isHeifMimeType(?string $mimeType): bool
    {
        return is_string($mimeType) && in_array(strtolower($mimeType), self::HEIF_MIME_TYPES, true);
    }

    public static function isHeifFile(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $mimeType = self::detectMimeType($path);

        if (self::isHeifMimeType($mimeType)) {
            return true;
        }

        return self::hasHeifBrand($path);
    }

    public static function detectMimeType(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mimeType = function_exists('mime_content_type') ? @mime_content_type($path) : null;

        if (is_string($mimeType) && $mimeType !== '') {
            return strtolower($mimeType);
        }

        if (! class_exists(\finfo::class)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        return is_string($mimeType) && $mimeType !== '' ? strtolower($mimeType) : null;
    }

    public static function hasHeifBrand(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return false;
        }

        $header = fread($handle, 512);
        fclose($handle);

        if (! is_string($header) || strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp') {
            return false;
        }

        $brands = [substr($header, 8, 4)];

        for ($offset = 16; $offset + 4 <= strlen($header); $offset += 4) {
            $brand = substr($header, $offset, 4);

            if (! preg_match('/^[A-Za-z0-9 ]{4}$/', $brand)) {
                continue;
            }

            $brands[] = $brand;
        }

        return count(array_intersect($brands, [
            'heic',
            'heix',
            'hevc',
            'hevx',
            'heis',
            'heim',
            'hevm',
            'hevs',
        ])) > 0;
    }
}
