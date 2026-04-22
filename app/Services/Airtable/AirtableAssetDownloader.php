<?php

namespace App\Services\Airtable;

use App\Support\MediaPath;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AirtableAssetDownloader
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function downloadMany(array $attachments, string $directory): array
    {
        $downloaded = [];

        foreach ($attachments as $attachment) {
            $path = $this->download($attachment, $directory);

            if ($path) {
                $downloaded[] = $path;
            }
        }

        return array_values(array_unique($downloaded));
    }

    public function firstImage(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (MediaPath::isImage($path)) {
                return $path;
            }
        }

        return null;
    }

    public function imagePaths(array $paths): array
    {
        return array_values(array_filter($paths, fn (?string $path) => MediaPath::isImage($path)));
    }

    private function download(array $attachment, string $directory): ?string
    {
        $url = $attachment['url'] ?? null;
        $attachmentId = $attachment['id'] ?? null;
        $filename = $attachment['filename'] ?? null;

        if (blank($url) || blank($attachmentId) || blank($filename)) {
            return null;
        }

        $relativePath = sprintf(
            '%s/%s-%s',
            trim($directory, '/'),
            $attachmentId,
            $this->sanitizeFilename($filename)
        );

        $disk = Storage::disk('public');

        if (! $disk->exists($relativePath)) {
            $contents = $this->http->timeout(120)->get($url)->throw()->body();
            $disk->put($relativePath, $contents);
        }

        return $relativePath;
    }

    private function sanitizeFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($basename);

        if ($slug === '') {
            $slug = 'bestand';
        }

        return $extension
            ? $slug . '.' . Str::lower($extension)
            : $slug;
    }
}
