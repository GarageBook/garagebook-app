<?php

namespace App\Services\Lifecycle;

use App\Models\Vehicle;
use App\Support\MediaPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class VehicleHealthCompletenessService
{
    /**
     * Completeness weights:
     * brand + model: 15, year: 15, current_km: 15, license_plate: 10,
     * photo: 20, maintenance log: 15, document/bewijsbestand: 10.
     */
    public function score(Vehicle $vehicle): int
    {
        $score = 0;

        if (filled($vehicle->brand) && filled($vehicle->model)) {
            $score += 15;
        }

        if (filled($vehicle->year)) {
            $score += 15;
        }

        if ((int) $vehicle->current_km > 0) {
            $score += 15;
        }

        if (filled($vehicle->license_plate)) {
            $score += 10;
        }

        if ($this->hasPhoto($vehicle)) {
            $score += 20;
        }

        if ($this->hasMaintenance($vehicle)) {
            $score += 15;
        }

        if ($this->hasDocument($vehicle)) {
            $score += 10;
        }

        return min(100, $score);
    }

    public function isProfileComplete(Vehicle $vehicle): bool
    {
        return filled($vehicle->brand)
            && filled($vehicle->model)
            && filled($vehicle->year)
            && (int) $vehicle->current_km > 0
            && $this->hasPhoto($vehicle);
    }

    public function hasPhoto(Vehicle $vehicle): bool
    {
        if (filled($vehicle->photo)) {
            return true;
        }

        return collect([
            ...Arr::wrap($vehicle->photos),
            ...Arr::wrap($vehicle->media_attachments),
        ])->contains(fn (mixed $path): bool => is_string($path) && MediaPath::isImage($path));
    }

    public function hasMaintenance(Vehicle $vehicle): bool
    {
        return $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs->isNotEmpty()
            : $vehicle->maintenanceLogs()->exists();
    }

    public function hasDocument(Vehicle $vehicle): bool
    {
        if ($vehicle->relationLoaded('documents') ? $vehicle->documents->isNotEmpty() : $vehicle->documents()->exists()) {
            return true;
        }

        $logs = $vehicle->relationLoaded('maintenanceLogs')
            ? $vehicle->maintenanceLogs
            : $vehicle->maintenanceLogs()->get();

        return $logs->contains(fn ($log): bool => filled($log->attachment)
            || collect([
                ...Arr::wrap($log->attachments),
                ...Arr::wrap($log->file_attachments),
            ])->contains(fn (mixed $path): bool => is_string($path) && filled($path)));
    }

    public function hasRecentActivity(Vehicle $vehicle): bool
    {
        $latestActivityAt = collect([
            $vehicle->updated_at,
            $vehicle->maintenanceLogs()->max('updated_at'),
            $vehicle->documents()->max('updated_at'),
            $vehicle->fuelLogs()->max('updated_at'),
        ])->filter()->map(fn (mixed $value): Carbon => $value instanceof Carbon ? $value : Carbon::parse($value))->max();

        return $latestActivityAt !== null && $latestActivityAt >= now()->subDays(90);
    }
}
