<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleDocumentMetadata
{
    public static function hydrate(array $data): array
    {
        $path = $data['file_path'] ?? null;

        if (! is_string($path) || blank($path)) {
            return $data;
        }

        $disk = Storage::disk('local');

        $data['original_filename'] = filled($data['original_filename'] ?? null)
            ? $data['original_filename']
            : Str::afterLast($path, '/');

        $data['mime_type'] = $disk->mimeType($path) ?: null;
        $data['file_size'] = $disk->size($path) ?: null;

        return $data;
    }
}
