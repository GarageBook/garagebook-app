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

        if (ImageUploadSupport::isHeifFile($sourcePath)) {
            return $this->convertHeifToJpeg($diskName, $path, $maxDimension, $quality);
        }

        if (ImageUploadSupport::isHeifPath($path)) {
            throw new RuntimeException('Het geuploade .heic-bestand is geen geldige Apple HEIC-afbeelding. Upload een geldige HEIC of kies JPG, PNG of WebP.');
        }

        $image = $this->loadGdImage($sourcePath);

        if ($image === null) {
            return null;
        }

        [$source, $width, $height] = $image;

        if (! $this->canLoadIntoMemory($width, $height)) {
            imagedestroy($source);

            return null;
        }

        [$targetWidth, $targetHeight] = $this->targetSize($width, $height, $maxDimension);
        $contents = $this->encodeJpegFromGd($source, $width, $height, $targetWidth, $targetHeight, $quality);
        imagedestroy($source);

        if ($contents === null) {
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

        $this->writeJpeg($diskName, $targetPath, $contents);

        if ($extensionChanged && $disk->exists($path)) {
            $disk->delete($path);
        }

        return $targetPath;
    }

    public function convertHeifToJpeg(string $diskName, string $path, int $maxDimension = 2200, int $quality = 82, bool $deleteOriginal = true): string
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            throw new RuntimeException('Het geuploade HEIC-bestand kon niet worden gevonden.');
        }

        $sourcePath = $disk->path($path);

        if (! ImageUploadSupport::isHeifFile($sourcePath)) {
            throw new RuntimeException('Het bestand is geen geldige Apple HEIC-afbeelding.');
        }

        $this->ensureHeifRuntimeIsAvailable();

        $targetPath = $this->availableTargetPath($diskName, $this->targetPath($path), $path);
        $temporaryPath = $this->temporaryTargetPath($targetPath);

        try {
            $contents = $this->encodeHeifAsJpeg($sourcePath, $maxDimension, $quality);
            $this->writeJpeg($diskName, $temporaryPath, $contents);
            $this->replaceWithValidatedJpeg($diskName, $temporaryPath, $targetPath);
        } catch (RuntimeException $exception) {
            if ($disk->exists($temporaryPath)) {
                $disk->delete($temporaryPath);
            }

            throw $exception;
        }

        if ($deleteOriginal && $targetPath !== $path && $disk->exists($path)) {
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

        if (ImageUploadSupport::isHeifFile($sourcePath) || ImageUploadSupport::isHeifPath($path)) {
            throw new RuntimeException('HEIC-afbeeldingen moeten eerst naar JPG worden geconverteerd en mogen niet als originele HEIC worden opgeslagen.');
        }

        $image = $this->loadGdImage($sourcePath);

        if ($image === null) {
            throw new RuntimeException('De afbeelding kon niet worden gelezen. Upload een geldige JPG, PNG, WebP, GIF of BMP.');
        }

        imagedestroy($image[0]);
    }

    public function ensureHeifRuntimeIsAvailable(): void
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            throw new RuntimeException('HEIC-afbeeldingen kunnen op deze server niet worden verwerkt omdat de PHP Imagick-extensie ontbreekt.');
        }

        $supportedFormats = array_map('strtoupper', \Imagick::queryFormats());

        if (! in_array('HEIC', $supportedFormats, true) && ! in_array('HEIF', $supportedFormats, true)) {
            throw new RuntimeException('HEIC-afbeeldingen kunnen op deze server niet worden verwerkt omdat ImageMagick/libheif geen HEIC ondersteunt.');
        }
    }

    public function isJpegFile(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $imageInfo = @getimagesize($path);

        return is_array($imageInfo) && ($imageInfo['mime'] ?? null) === 'image/jpeg';
    }

    private function loadGdImage(string $sourcePath): ?array
    {
        $imageInfo = @getimagesize($sourcePath);

        if (! is_array($imageInfo)) {
            return null;
        }

        [$width, $height] = $imageInfo;
        $mimeType = $imageInfo['mime'] ?? null;

        if (! $mimeType || $width < 1 || $height < 1) {
            return null;
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

    private function encodeHeifAsJpeg(string $sourcePath, int $maxDimension, int $quality): string
    {
        try {
            $imagick = new \Imagick($sourcePath);
            $imagick = $imagick->coalesceImages();
            $imagick->setIteratorIndex(0);
            $imagick->autoOrient();
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $imagick->setImageBackgroundColor('white');
            $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            if ($width < 1 || $height < 1 || ! $this->canLoadIntoMemory($width, $height)) {
                throw new RuntimeException('De HEIC-afbeelding is te groot om veilig te verwerken.');
            }

            [$targetWidth, $targetHeight] = $this->targetSize($width, $height, $maxDimension);

            if ($targetWidth !== $width || $targetHeight !== $height) {
                $imagick->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1, true);
            }

            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(max(1, min($quality, 100)));
            $imagick->stripImage();

            $blob = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new RuntimeException('De HEIC-afbeelding kon niet naar JPG worden geconverteerd.', previous: $exception);
        }

        if (! is_string($blob) || $blob === '') {
            throw new RuntimeException('De HEIC-afbeelding leverde geen geldige JPG-output op.');
        }

        return $blob;
    }

    private function encodeJpegFromGd($source, int $width, int $height, int $targetWidth, int $targetHeight, int $quality): ?string
    {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas) {
            return null;
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $encoded = imagejpeg($canvas, null, max(1, min($quality, 100)));
        $contents = ob_get_clean();

        imagedestroy($canvas);

        return $encoded && is_string($contents) && $contents !== '' ? $contents : null;
    }

    private function writeJpeg(string $diskName, string $path, string $contents): void
    {
        $disk = Storage::disk($diskName);
        $disk->put($path, $contents);

        if (! $this->isJpegFile($disk->path($path))) {
            $disk->delete($path);

            throw new RuntimeException('De geconverteerde afbeelding is geen geldige JPEG.');
        }
    }

    private function replaceWithValidatedJpeg(string $diskName, string $temporaryPath, string $targetPath): void
    {
        $disk = Storage::disk($diskName);

        if (! $this->isJpegFile($disk->path($temporaryPath))) {
            throw new RuntimeException('De tijdelijke geconverteerde afbeelding is geen geldige JPEG.');
        }

        $contents = $disk->get($temporaryPath);
        $disk->put($targetPath, $contents);
        $disk->delete($temporaryPath);

        if (! $this->isJpegFile($disk->path($targetPath))) {
            $disk->delete($targetPath);

            throw new RuntimeException('De definitieve geconverteerde afbeelding is geen geldige JPEG.');
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

        if ($extension === 'jpg' && Str::endsWith($path, '.jpg')) {
            return $path;
        }

        return Str::beforeLast($path, '.').'.jpg';
    }

    private function availableTargetPath(string $diskName, string $targetPath, string $sourcePath): string
    {
        $disk = Storage::disk($diskName);

        if ($targetPath === $sourcePath || ! $disk->exists($targetPath)) {
            return $targetPath;
        }

        $directory = dirname($targetPath);
        $filename = pathinfo($targetPath, PATHINFO_FILENAME);
        $extension = pathinfo($targetPath, PATHINFO_EXTENSION) ?: 'jpg';

        for ($attempt = 1; $attempt <= 100; $attempt++) {
            $candidate = $filename.'-'.$attempt.'.'.$extension;
            $candidatePath = $directory === '.' ? $candidate : $directory.'/'.$candidate;

            if (! $disk->exists($candidatePath)) {
                return $candidatePath;
            }
        }

        return ($directory === '.' ? $filename : $directory.'/'.$filename).'-'.Str::uuid().'.'.$extension;
    }

    private function temporaryTargetPath(string $targetPath): string
    {
        $directory = dirname($targetPath);
        $filename = pathinfo($targetPath, PATHINFO_FILENAME).'.'.Str::uuid().'.tmp.jpg';

        return $directory === '.' ? $filename : $directory.'/'.$filename;
    }
}
