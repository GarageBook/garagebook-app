<?php

namespace App\Services\Growth\Community2026;

interface CommunityDiscoveryProvider
{
    /**
     * @return list<string>
     */
    public function urls(): array;

    public function subtype(): string;
}
