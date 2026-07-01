<?php

namespace App\Services\Growth\Campaigns;

use InvalidArgumentException;

class CampaignRegistry
{
    /**
     * @param  array<int, CampaignDefinition>  $definitions
     */
    public function __construct(
        private readonly array $definitions,
    ) {}

    /**
     * @return array<int, CampaignDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function forSlug(string $slug): CampaignDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->slug() === $slug) {
                return $definition;
            }
        }

        throw new InvalidArgumentException('Onbekende campaign: '.$slug);
    }
}
