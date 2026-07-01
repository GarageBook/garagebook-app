<?php

namespace App\Services\Growth\Partner2026;

use App\Services\Growth\Community2026EnrichmentService;
use App\Services\Growth\Discovery\DiscoveryNormalizer;
use App\Services\Growth\GrowthProspectNormalizer;

class Partner2026EnrichmentService extends Community2026EnrichmentService
{
    public function __construct(
        Partner2026ImportService $importer,
        DiscoveryNormalizer $normalizer,
        GrowthProspectNormalizer $prospectNormalizer,
    ) {
        parent::__construct($importer, $normalizer, $prospectNormalizer);
    }
}
