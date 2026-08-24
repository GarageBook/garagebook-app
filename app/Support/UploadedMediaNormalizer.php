<?php

namespace App\Support;

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
        $optimizedPath = $this->processor->optimizeImage($diskName, $path, $maxDimension, $quality);

        if ($optimizedPath !== null) {
            return $optimizedPath;
        }

        if (ImageUploadSupport::isHeifPath($path)) {
            throw new RuntimeException('HEIF/HEIC-afbeeldingen kunnen op deze server niet worden gelezen. Controleer of Imagick met libheif is geinstalleerd en upload een geldige afbeelding.');
        }

        $this->processor->validateDecodable($diskName, $path);

        return $path;
    }
}
