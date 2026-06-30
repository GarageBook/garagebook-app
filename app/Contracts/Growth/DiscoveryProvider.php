<?php

namespace App\Contracts\Growth;

use App\Data\Growth\DiscoveryResult;

interface DiscoveryProvider
{
    /**
     * @return array<int, DiscoveryResult>
     */
    public function discover(): array;
}
