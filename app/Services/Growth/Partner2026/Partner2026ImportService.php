<?php

namespace App\Services\Growth\Partner2026;

use App\Services\Growth\Community2026ImportService;
use App\Services\Growth\GrowthOutreachEventLogger;
use App\Services\Growth\GrowthProspectNormalizer;

class Partner2026ImportService extends Community2026ImportService
{
    public function __construct(
        GrowthProspectNormalizer $normalizer,
        GrowthOutreachEventLogger $events,
    ) {
        parent::__construct(
            $normalizer,
            $events,
            'partner2026',
            'Partner2026',
            'Gespecialiseerde bedrijven rondom onderhoud, onderdelen, banden, detailing, tuning, vering, remmen, oldtimers, campers, 4x4 en motoraccessoires.',
        );
    }
}
