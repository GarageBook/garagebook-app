<?php

namespace App\Console\Commands;

use App\Models\MaintenanceLog;
use App\Models\TripLog;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\ImageUploadSupport;
use App\Support\MediaPath;
use App\Support\RasterImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RepairHeicMediaCommand extends Command
{
    protected $signature = 'garagebook:repair-heic-media {--dry-run : Rapporteer zonder bestanden of database te wijzigen}';

    protected $description = 'Convert existing GarageBook HEIC media paths to validated JPG files.';

    public function handle(RasterImageProcessor $processor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $candidates = $this->candidates();
        $heicCandidates = array_values(array_filter($candidates, fn (array $candidate): bool => $this->shouldInspect($candidate)));

        $this->info(sprintf('HEIC media candidates: %d', count($heicCandidates)));

        if ($heicCandidates === []) {
            return self::SUCCESS;
        }

        $requiresRuntime = false;
        $summary = [
            'converted' => 0,
            'dry_run' => 0,
            'missing' => 0,
            'not_heic' => 0,
            'failed' => 0,
        ];

        foreach ($heicCandidates as $candidate) {
            $disk = Storage::disk($candidate['disk']);
            $path = $candidate['path'];
            $exists = $disk->exists($path);
            $physicalPath = $exists ? $disk->path($path) : $disk->path($path);
            $isHeic = $exists && ImageUploadSupport::isHeifFile($physicalPath);
            $mimeType = $exists ? (ImageUploadSupport::detectMimeType($physicalPath) ?: 'unknown') : 'missing';
            $targetPath = $this->targetPath($path);

            $this->line(sprintf(
                '[%s #%s %s] %s | disk=%s | physical=%s | detected=%s | proposed=%s',
                $candidate['model'],
                $candidate['id'],
                $candidate['field'],
                $path,
                $candidate['disk'],
                $physicalPath,
                $isHeic ? 'heic' : $mimeType,
                $targetPath,
            ));

            if (! $exists) {
                $summary['missing']++;
                $this->warn('  missing source file; leaving database unchanged.');

                continue;
            }

            if (! $isHeic) {
                $summary['not_heic']++;
                $this->warn('  file is not a content-detected HEIC; leaving database unchanged.');

                continue;
            }

            $requiresRuntime = true;

            if ($dryRun) {
                $summary['dry_run']++;

                continue;
            }
        }

        if ($dryRun) {
            $this->printSummary($summary);

            return self::SUCCESS;
        }

        if ($requiresRuntime) {
            try {
                $processor->ensureHeifRuntimeIsAvailable();
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());
                $this->error('Geen bestanden of databasewaarden gewijzigd. Installeer Imagick/ImageMagick met HEIC/libheif-support en draai daarna eerst --dry-run.');

                return self::FAILURE;
            }
        }

        foreach ($heicCandidates as $candidate) {
            $disk = Storage::disk($candidate['disk']);
            $path = $candidate['path'];

            if (! $disk->exists($path)) {
                continue;
            }

            $physicalPath = $disk->path($path);

            if (! ImageUploadSupport::isHeifFile($physicalPath)) {
                continue;
            }

            try {
                $newPath = $processor->convertHeifToJpeg($candidate['disk'], $path, deleteOriginal: false);

                DB::transaction(function () use ($candidate, $newPath): void {
                    $record = $candidate['record']::query()->lockForUpdate()->findOrFail($candidate['id']);
                    $this->applyPathReplacement($record, $candidate['field'], $candidate['path'], $newPath);
                    $record->save();
                });

                if ($newPath !== $path && $disk->exists($path)) {
                    $disk->delete($path);
                }

                $summary['converted']++;
                $this->info(sprintf('  converted: %s', $newPath));
            } catch (Throwable $exception) {
                $summary['failed']++;
                $this->error(sprintf('  failed: %s', $exception->getMessage()));
            }
        }

        $this->printSummary($summary);

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{record: class-string<Model>, model: string, id: int, field: string, path: string, disk: string}>
     */
    private function candidates(): array
    {
        $candidates = [];

        Vehicle::query()->select(['id', 'photo', 'photos', 'media_attachments'])->each(function (Vehicle $vehicle) use (&$candidates): void {
            $this->pushPath($candidates, Vehicle::class, 'Vehicle', $vehicle->id, 'photo', $vehicle->photo, 'public');

            foreach (Arr::wrap($vehicle->photos) as $path) {
                $this->pushPath($candidates, Vehicle::class, 'Vehicle', $vehicle->id, 'photos', $path, 'public');
            }

            foreach (Arr::wrap($vehicle->media_attachments) as $path) {
                $this->pushPath($candidates, Vehicle::class, 'Vehicle', $vehicle->id, 'media_attachments', $path, 'public');
            }
        });

        MaintenanceLog::query()->select(['id', 'attachments', 'media_attachments', 'file_attachments'])->each(function (MaintenanceLog $log) use (&$candidates): void {
            foreach ($log->attachments as $path) {
                $this->pushPath($candidates, MaintenanceLog::class, 'MaintenanceLog', $log->id, 'attachments', $path, 'public');
            }
        });

        TripLog::query()->select(['id', 'photos'])->each(function (TripLog $tripLog) use (&$candidates): void {
            foreach (Arr::wrap($tripLog->photos) as $path) {
                $this->pushPath($candidates, TripLog::class, 'TripLog', $tripLog->id, 'photos', $path, 'local');
            }
        });

        VehicleDocument::query()->select(['id', 'file_path'])->each(function (VehicleDocument $document) use (&$candidates): void {
            $this->pushPath($candidates, VehicleDocument::class, 'VehicleDocument', $document->id, 'file_path', $document->file_path, 'local');
        });

        return $candidates;
    }

    private function pushPath(array &$candidates, string $record, string $model, int $id, string $field, mixed $path, string $disk): void
    {
        if (! is_string($path) || blank($path)) {
            return;
        }

        if (! MediaPath::isImage($path) && ! ImageUploadSupport::isHeifPath($path)) {
            return;
        }

        $candidates[] = compact('record', 'model', 'id', 'field', 'path', 'disk');
    }

    private function shouldInspect(array $candidate): bool
    {
        if (ImageUploadSupport::isHeifPath($candidate['path'])) {
            return true;
        }

        $disk = Storage::disk($candidate['disk']);

        return $disk->exists($candidate['path']) && ImageUploadSupport::isHeifFile($disk->path($candidate['path']));
    }

    private function applyPathReplacement(Model $record, string $field, string $oldPath, string $newPath): void
    {
        if ($field === 'photo' || $field === 'file_path') {
            $record->{$field} = $newPath;

            if ($record instanceof VehicleDocument) {
                $disk = Storage::disk('local');
                $record->mime_type = $disk->mimeType($newPath) ?: 'image/jpeg';
                $record->file_size = $disk->size($newPath) ?: null;
            }

            return;
        }

        $paths = array_map(
            fn (mixed $path): mixed => $path === $oldPath ? $newPath : $path,
            Arr::wrap($record->{$field})
        );

        $record->{$field} = array_values($paths);
    }

    private function targetPath(string $path): string
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'jpg' && Str::endsWith($path, '.jpg')) {
            return $path;
        }

        return Str::beforeLast($path, '.').'.jpg';
    }

    private function printSummary(array $summary): void
    {
        $this->info(sprintf(
            'Summary: converted=%d dry_run=%d missing=%d not_heic=%d failed=%d',
            $summary['converted'],
            $summary['dry_run'],
            $summary['missing'],
            $summary['not_heic'],
            $summary['failed'],
        ));
    }
}
