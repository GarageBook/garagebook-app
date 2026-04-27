<?php

namespace App\Support;

use App\Models\MaintenanceLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MaintenanceMediaOptimizer
{
    public function optimizeLog(MaintenanceLog $log, int $maxDimension = 2200, int $quality = 82): void
    {
        $attachments = $log->attachments;

        if ($attachments === []) {
            return;
        }

        $optimized = [];
        $changed = false;

        foreach ($attachments as $attachment) {
            $optimizedPath = $this->optimizePublicImage($attachment, $maxDimension, $quality) ?? $attachment;

            if ($optimizedPath !== $attachment) {
                $changed = true;
            }

            $optimized[] = $optimizedPath;
        }

        if (! $changed) {
            return;
        }

        $log->attachments = $optimized;
        $log->saveQuietly();
    }

    public function optimizePublicImage(string $path, int $maxDimension = 2200, int $quality = 82): ?string
    {
        if (! MediaPath::isImage($path) || Str::endsWith(Str::lower($path), '.svg')) {
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

        $source = $this->createSourceImage($sourcePath, $mimeType);

        if (! $source) {
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

        imagecopyresampled(
            $canvas,
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

        if ($targetPath === $path && ! $dimensionsChanged && $optimizedBytes >= $originalBytes) {
            return null;
        }

        $disk->put($targetPath, $contents);

        if ($targetPath !== $path && $disk->exists($path)) {
            $disk->delete($path);
        }

        return $targetPath;
    }

    private function createSourceImage(string $path, string $mimeType): mixed
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

    private function targetSize(int $width, int $height, int $maxDimension): array
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

    private function targetPath(string $path): string
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return $path;
        }

        return Str::beforeLast($path, '.') . '.jpg';
    }
}
