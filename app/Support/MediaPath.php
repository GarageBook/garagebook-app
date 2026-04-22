<?php

namespace App\Support;

use Illuminate\Support\Str;

class MediaPath
{
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
