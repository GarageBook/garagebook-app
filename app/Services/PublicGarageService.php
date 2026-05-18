<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
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

        $logs = $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs
            : $vehicle->maintenanceLogs()->latest('maintenance_date')->latest('id')->get();

        if ($logs->isEmpty()) {
            return false;
        }

        if ($this->publicVehiclePhotos($vehicle) !== []) {
            return true;
        }

        if (filled($vehicle->notes)) {
            return true;
        }

        return $logs->contains(fn ($log) => filled($log->description));
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
            'Deze publieke GarageBook-pagina toont de onderhoudshistorie van deze %s. Je ziet onderhoudsmomenten, kilometerstanden en werkzaamheden die door de eigenaar zijn bijgehouden.',
            $this->publicVehicleName($vehicle),
        );
    }

    public function publicVehiclePhotos(Vehicle $vehicle): array
    {
        return array_values(array_unique(array_filter([
            $vehicle->photo,
            ...Arr::wrap($vehicle->photos),
        ])));
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
}
