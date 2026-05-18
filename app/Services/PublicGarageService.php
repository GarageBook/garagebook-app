<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\ImageThumbnail;
use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicGarageService
{
    public function findPublicVehicleBySlug(string $publicSlug): ?Vehicle
    {
        return Vehicle::query()
            ->with([
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->where('public_slug', trim($publicSlug))
            ->where('is_public', true)
            ->first();
    }

    public function findLegacyPublicVehicle(string $username, string $vehicleSlug): ?Vehicle
    {
        $user = User::query()
            ->get(['id', 'name'])
            ->first(fn (User $user) => Str::slug(trim($user->name)) === trim($username));

        if (! $user) {
            return null;
        }

        return Vehicle::query()
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->get()
            ->first(fn (Vehicle $vehicle) => $this->legacyVehicleSlug($vehicle) === trim($vehicleSlug));
    }

    public function publicUrl(Vehicle $vehicle): string
    {
        $publicSlug = $vehicle->public_slug ?: $this->ensurePublicSlug($vehicle);

        return url('/garage/' . $publicSlug);
    }

    public function ensurePublicSlug(Vehicle $vehicle): string
    {
        if (filled($vehicle->public_slug)) {
            return $vehicle->public_slug;
        }

        $vehicle->forceFill([
            'public_slug' => $this->generatePublicSlug($vehicle, $vehicle->exists ? $vehicle->id : null),
        ]);

        $vehicle->saveQuietly();

        return (string) $vehicle->public_slug;
    }

    public function generatePublicSlug(Vehicle $vehicle, ?int $ignoreVehicleId = null): string
    {
        $baseSlug = $this->publicSlugBase($vehicle);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($candidate, $ignoreVehicleId)) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function shouldIndex(Vehicle $vehicle): bool
    {
        if (! $vehicle->is_public || blank($vehicle->public_slug)) {
            return false;
        }

        $timelineItems = $this->publicTimelineItems($vehicle);

        if ($timelineItems === []) {
            return false;
        }

        if ($this->publicVehiclePhotos($vehicle) !== []) {
            return true;
        }

        return collect($timelineItems)->contains(
            fn (array $item) => filled($item['description']) || filled($item['notes'])
        );
    }

    public function indexableVehicles(): Collection
    {
        return Vehicle::query()
            ->with([
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->where('is_public', true)
            ->whereNotNull('public_slug')
            ->whereHas('maintenanceLogs')
            ->orderBy('public_slug')
            ->get()
            ->filter(fn (Vehicle $vehicle) => $this->shouldIndex($vehicle))
            ->values();
    }

    public function publicVehicleName(Vehicle $vehicle): string
    {
        return implode(' ', array_filter([
            $vehicle->year,
            $vehicle->brand,
            $vehicle->model,
            $this->variantForDisplay($vehicle),
        ]));
    }

    public function publicVehicleHeading(Vehicle $vehicle): string
    {
        return implode(' ', array_filter([
            $vehicle->brand,
            $vehicle->model,
            $this->variantForDisplay($vehicle),
        ]));
    }

    public function publicIntroText(Vehicle $vehicle): string
    {
        return sprintf(
            'Deze publieke GarageBook-pagina toont de onderhoudshistorie van deze %s. Je ziet onderhoudsmomenten, kilometerstanden en uitgevoerde werkzaamheden, zonder persoonsgegevens van de eigenaar.',
            $this->publicVehicleName($vehicle),
        );
    }

    public function publicVehiclePhotos(Vehicle $vehicle): array
    {
        return collect([
            $vehicle->photo,
            ...Arr::wrap($vehicle->photos),
        ])
            ->filter(fn ($path) => is_string($path) && filled($path) && MediaPath::isImage($path))
            ->map(fn (string $path) => ltrim($path, '/'))
            ->unique()
            ->values()
            ->map(function (string $path): ?array {
                $thumbnailPath = ImageThumbnail::path($path, 960);

                if ($thumbnailPath === null && ! $this->publicStoragePathExists($path)) {
                    return null;
                }

                return [
                    'path' => $path,
                    'thumbnail_url' => asset('storage/' . ltrim($thumbnailPath ?: $path, '/')),
                    'url' => asset('storage/' . ltrim($path, '/')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function publicTimelineItems(Vehicle $vehicle): array
    {
        $logs = $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs
            : $vehicle->maintenanceLogs()->latest('maintenance_date')->latest('id')->get();

        return $logs->map(function ($log) use ($vehicle): array {
            $publicAttachments = $vehicle->share_attachments_publicly
                ? collect($log->media_attachments)
                    ->filter(fn ($path) => is_string($path) && filled($path) && MediaPath::isImage($path))
                    ->map(fn (string $path): ?array => $this->publicAttachmentImage($path, $vehicle))
                    ->filter()
                    ->values()
                    ->all()
                : [];

            return [
                'date_label' => $log->maintenance_date?->format('d-m-Y'),
                'description' => trim((string) $log->description),
                'km_label' => $log->km_reading > 0
                    ? app(DistanceUnitService::class)->formatFromKilometers($log->km_reading, $vehicle->distance_unit, 0)
                    : null,
                'cost_label' => $vehicle->share_costs_publicly && $log->cost !== null
                    ? '€ ' . number_format((float) $log->cost, 2, ',', '.')
                    : null,
                'notes' => trim((string) $log->notes) !== '' ? trim((string) $log->notes) : null,
                'public_attachments' => $publicAttachments,
            ];
        })->all();
    }

    public function publicStats(Vehicle $vehicle): array
    {
        $logs = $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs
            : $vehicle->maintenanceLogs()->latest('maintenance_date')->latest('id')->get();

        $lastUpdatedAt = $logs->first()?->maintenance_date ?? $vehicle->updated_at;

        return [
            'maintenance_count' => $logs->count(),
            'last_updated_label' => $lastUpdatedAt?->format('d-m-Y'),
        ];
    }

    public function typeSpecificLandingUrl(Vehicle $vehicle): ?string
    {
        $vehicleType = $this->normalizedVehicleType($vehicle);

        return match ($vehicleType) {
            'motorcycle' => 'https://garagebook.nl/motor-onderhoud-app/',
            'car' => 'https://garagebook.nl/auto-onderhoud-app/',
            default => null,
        };
    }

    public function normalizedVehicleType(Vehicle $vehicle): ?string
    {
        $candidates = array_filter([
            $vehicle->getAttribute('vehicle_type'),
            $vehicle->getAttribute('type'),
            $vehicle->getAttribute('category'),
            $vehicle->getAttribute('categorie'),
        ], fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeVehicleTypeValue($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    public function legacyVehicleSlug(Vehicle $vehicle): string
    {
        return Str::slug(trim($vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model));
    }

    private function publicSlugBase(Vehicle $vehicle): string
    {
        $base = Str::slug(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->brand,
            $vehicle->model,
            $this->variantForSlug($vehicle),
        ])));

        return $base !== '' ? $base : 'garage-vehicle';
    }

    private function normalizeVehicleTypeValue(string $value): ?string
    {
        $tokens = array_values(array_filter(explode('-', Str::slug($value))));

        if (array_intersect($tokens, ['motorcycle', 'motor', 'motorfiets', 'bike']) !== []) {
            return 'motorcycle';
        }

        if (array_intersect($tokens, ['car', 'auto']) !== []) {
            return 'car';
        }

        return null;
    }

    private function variantForDisplay(Vehicle $vehicle): ?string
    {
        $variant = trim((string) $vehicle->getAttribute('display_variant'));

        return $variant !== '' ? $variant : null;
    }

    private function variantForSlug(Vehicle $vehicle): ?string
    {
        $variant = $this->variantForDisplay($vehicle);

        if ($variant === null) {
            return null;
        }

        $modelSlug = Str::slug((string) $vehicle->model);
        $variantSlug = Str::slug($variant);

        if ($modelSlug !== '' && $variantSlug !== '' && str_contains($modelSlug, $variantSlug)) {
            return null;
        }

        return $variant;
    }

    private function slugExists(string $slug, ?int $ignoreVehicleId = null): bool
    {
        return Vehicle::query()
            ->when($ignoreVehicleId, fn ($query) => $query->whereKeyNot($ignoreVehicleId))
            ->where('public_slug', $slug)
            ->exists();
    }

    private function publicAttachmentImage(string $path, Vehicle $vehicle): ?array
    {
        $path = ltrim($path, '/');
        $thumbnailPath = ImageThumbnail::path($path, 720) ?: $path;

        if (! $this->publicStoragePathExists($path)) {
            return null;
        }

        return [
            'alt' => 'Publieke foto bij onderhoud van ' . $this->publicVehicleName($vehicle),
            'thumbnail_url' => asset('storage/' . ltrim($thumbnailPath, '/')),
            'url' => asset('storage/' . $path),
        ];
    }

    private function publicStoragePathExists(string $path): bool
    {
        return \Illuminate\Support\Facades\Storage::disk('public')->exists(ltrim($path, '/'));
    }
}
