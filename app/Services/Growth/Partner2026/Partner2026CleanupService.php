<?php

namespace App\Services\Growth\Partner2026;

use App\Services\Growth\Community2026CleanupService;
use App\Services\Growth\GrowthProspectNormalizer;

class Partner2026CleanupService extends Community2026CleanupService
{
    public function __construct(
        GrowthProspectNormalizer $normalizer,
    ) {
        parent::__construct(
            $normalizer,
            'partner2026',
            'Partner2026',
            'Gespecialiseerde bedrijven rondom onderhoud, onderdelen, banden, detailing, tuning, vering, remmen, oldtimers, campers, 4x4 en motoraccessoires.',
        );
    }
}
