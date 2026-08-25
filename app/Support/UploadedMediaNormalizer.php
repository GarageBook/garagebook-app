<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadedMediaNormalizer
{
    public function __construct(
        private readonly RasterImageProcessor $processor,
    ) {}

    public function normalizeNullableImage(?string $path, string $diskName, int $maxDimension = 2200, int $quality = 82): ?string
    {
        if (blank($path) || ! is_string($path)) {
            return $path;
        }

        return $this->normalizeImagePath($path, $diskName, $maxDimension, $quality);
    }

    /**
     * @param  array<mixed>  $paths
     * @return list<string>
     */
    public function normalizeImageList(array $paths, string $diskName, int $maxDimension = 2200, int $quality = 82): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            if (! is_string($path) || blank($path)) {
                continue;
            }

            $normalized[] = $this->normalizeImagePath($path, $diskName, $maxDimension, $quality);
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $paths
     * @return list<string>
     */
    public function normalizeMixedAttachmentList(array $paths, string $diskName, int $maxDimension = 2200, int $quality = 82): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            if (! is_string($path) || blank($path)) {
                continue;
            }

            $normalized[] = MediaPath::isImage($path)
                ? $this->normalizeImagePath($path, $diskName, $maxDimension, $quality)
                : $path;
        }

        return $normalized;
    }

    private function normalizeImagePath(string $path, string $diskName, int $maxDimension, int $quality): string
    {
        $disk = Storage::disk($diskName);
        $fullPath = $disk->exists($path) ? $disk->path($path) : null;
        $isHeifUpload = is_string($fullPath) && ImageUploadSupport::isHeifFile($fullPath);

        try {
            $optimizedPath = $this->processor->optimizeImage($diskName, $path, $maxDimension, $quality);
        } catch (RuntimeException $exception) {
            if ($isHeifUpload || ImageUploadSupport::isHeifPath($path)) {
                throw $exception;
            }

            throw $exception;
        }

        if ($optimizedPath !== null) {
            return $optimizedPath;
        }

        if ($isHeifUpload || ImageUploadSupport::isHeifPath($path)) {
            throw new RuntimeException('HEIC-afbeeldingen konden niet naar JPG worden geconverteerd. Upload een geldige HEIC of kies JPG, PNG of WebP.');
        }

        $this->processor->validateDecodable($diskName, $path);

        return $path;
    }
}
