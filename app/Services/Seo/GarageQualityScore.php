<?php

namespace App\Services\Seo;

use App\Models\Vehicle;
use App\Services\PublicGarageService;

class GarageQualityScore
{
    public const SHORT_DESCRIPTION_MIN_LENGTH = 12;

    public const USEFUL_SHORT_DESCRIPTIONS = [
        'apk',
        'mot',
    ];

    public function __construct(
        private readonly PublicGarageService $publicGarageService,
    ) {}

    public function score(Vehicle $vehicle): array
    {
        $timelineItems = $this->publicGarageService->publicTimelineItems($vehicle);
        $photos = $this->publicGarageService->publicVehiclePhotos($vehicle);
        $maintenanceCount = count($timelineItems);
        $usableDescriptions = $this->usableDescriptionCount($timelineItems);
        $datedOrMeteredCount = collect($timelineItems)
            ->filter(fn (array $item): bool => filled($item['date_label'] ?? null) || (bool) ($item['has_km_reading'] ?? false))
            ->count();

        $parts = [
            'vehicle_identity' => [
                'label' => 'Merk en model gevuld',
                'points' => filled($vehicle->brand) && filled($vehicle->model) ? 15 : 0,
                'max' => 15,
                'reason_code' => 'missing_vehicle_identity',
            ],
            'vehicle_photo' => [
                'label' => 'Voertuigfoto aanwezig',
                'points' => $photos !== [] ? 15 : 0,
                'max' => 15,
                'reason_code' => 'missing_photo',
            ],
            'one_maintenance_log' => [
                'label' => 'Minimaal een onderhoudslog',
                'points' => $maintenanceCount >= 1 ? 20 : 0,
                'max' => 20,
                'reason_code' => 'no_maintenance_logs',
            ],
            'three_maintenance_logs' => [
                'label' => 'Minimaal drie onderhoudslogs',
                'points' => $maintenanceCount >= 3 ? 15 : 0,
                'max' => 15,
                'reason_code' => 'few_maintenance_logs',
            ],
            'usable_log_descriptions' => [
                'label' => 'Bruikbare logomschrijvingen',
                'points' => $maintenanceCount > 0 && $usableDescriptions === $maintenanceCount ? 15 : 0,
                'max' => 15,
                'reason_code' => 'short_log_descriptions',
            ],
            'dates_or_mileage' => [
                'label' => 'Onderhoudsdatums of kilometerstanden gevuld',
                'points' => $maintenanceCount > 0 && $datedOrMeteredCount === $maintenanceCount ? 10 : 0,
                'max' => 10,
                'reason_code' => 'missing_dates_or_mileage',
            ],
            'additional_vehicle_profile' => [
                'label' => 'Aanvullende voertuiggegevens gevuld',
                'points' => $this->hasAdditionalProfile($vehicle) ? 10 : 0,
                'max' => 10,
                'reason_code' => 'incomplete_vehicle_profile',
            ],
        ];

        $missing = collect($parts)
            ->filter(fn (array $part): bool => $part['points'] < $part['max'])
            ->map(fn (array $part): string => $part['label'])
            ->values()
            ->all();

        $reasonCodes = collect($parts)
            ->filter(fn (array $part): bool => $part['points'] < $part['max'])
            ->pluck('reason_code')
            ->values()
            ->all();

        return [
            'score' => min(100, (int) collect($parts)->sum('points')),
            'breakdown' => $parts,
            'missing' => $missing,
            'reason_codes' => $reasonCodes,
            'facts' => [
                'maintenance_count' => $maintenanceCount,
                'usable_description_count' => $usableDescriptions,
                'dated_or_metered_count' => $datedOrMeteredCount,
                'photo_count' => count($photos),
            ],
        ];
    }

    public function hasUsableDescription(?string $description): bool
    {
        $description = trim((string) $description);

        if ($description === '') {
            return false;
        }

        $normalized = str($description)->lower()->trim()->toString();

        if (in_array($normalized, self::USEFUL_SHORT_DESCRIPTIONS, true)) {
            return true;
        }

        return mb_strlen($description) >= self::SHORT_DESCRIPTION_MIN_LENGTH;
    }

    private function usableDescriptionCount(array $timelineItems): int
    {
        return collect($timelineItems)
            ->filter(fn (array $item): bool => $this->hasUsableDescription($item['description'] ?? null))
            ->count();
    }

    private function hasAdditionalProfile(Vehicle $vehicle): bool
    {
        $filled = collect([
            $vehicle->year,
            $vehicle->display_variant,
            $vehicle->powertrain_type,
            (int) $vehicle->current_km > 0 ? $vehicle->current_km : null,
        ])->filter(fn (mixed $value): bool => filled($value))->count();

        return $filled >= 2;
    }
}
