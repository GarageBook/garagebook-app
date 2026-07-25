<?php

namespace App\Services\Lifecycle\Conditions;

use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Services\Seo\GarageQualityScore;

class PublicGarageLifecycleConditions
{
    public function __construct(
        private readonly PublicGarageService $publicGarageService,
        private readonly GarageQualityScore $qualityScore,
    ) {}

    public function publicVehicleWithoutPhoto(Vehicle $vehicle): bool
    {
        return $this->eligiblePublicVehicle($vehicle)
            && $this->publicGarageService->publicVehiclePhotos($vehicle) === [];
    }

    public function publicVehicleWithoutMaintenance(Vehicle $vehicle): bool
    {
        return $this->eligiblePublicVehicle($vehicle)
            && $this->publicGarageService->publicTimelineItems($vehicle) === [];
    }

    public function lowGarageQualityScore(Vehicle $vehicle, int $threshold = 60): bool
    {
        return $this->eligiblePublicVehicle($vehicle)
            && ($this->qualityScore->score($vehicle)['score'] ?? 0) < $threshold;
    }

    public function eligiblePublicVehicle(Vehicle $vehicle): bool
    {
        return (bool) $vehicle->is_public
            && filled($vehicle->public_slug)
            && ! $this->publicGarageService->isOutreachDemoVehicle($vehicle);
    }
}
