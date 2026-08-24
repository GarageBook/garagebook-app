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
}
