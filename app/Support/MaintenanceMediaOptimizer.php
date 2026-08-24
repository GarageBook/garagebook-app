<?php

namespace App\Support;

use App\Models\MaintenanceLog;
use Illuminate\Support\Str;

class MaintenanceMediaOptimizer
{
    public function __construct(
        private readonly RasterImageProcessor $processor,
    ) {}

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

        return $this->processor->optimizeImage('public', $path, $maxDimension, $quality);
    }
}
