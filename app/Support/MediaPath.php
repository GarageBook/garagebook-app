<?php

namespace App\Support;

use Illuminate\Support\Str;

class MediaPath
{
    public static function mimeType(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return match (Str::lower(pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'm4v' => 'video/x-m4v',
            'avi' => 'video/x-msvideo',
            'pdf' => 'application/pdf',
            default => null,
        };
    }

    public static function isImage(?string $path): bool
    {
        return self::matchesExtension($path, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg']);
    }

    public static function isVideo(?string $path): bool
    {
        return self::matchesExtension($path, ['mp4', 'mov', 'webm', 'm4v', 'avi']);
    }

    public static function isPdf(?string $path): bool
    {
        return self::matchesExtension($path, ['pdf']);
    }

    public static function label(?string $path): string
    {
        if (blank($path)) {
            return 'Bestand';
        }

        return basename((string) $path);
    }

    private static function matchesExtension(?string $path, array $extensions): bool
    {
        if (blank($path)) {
            return false;
        }

        return Str::endsWith(Str::lower((string) $path), collect($extensions)->map(
            fn (string $extension) => '.' . $extension
        )->all());
    }
}
