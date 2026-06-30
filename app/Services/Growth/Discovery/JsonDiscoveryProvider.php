<?php

namespace App\Services\Growth\Discovery;

use App\Contracts\Growth\DiscoveryProvider;
use App\Data\Growth\DiscoveryResult;

class JsonDiscoveryProvider implements DiscoveryProvider
{
    public function __construct(private readonly string $path) {}

    public function discover(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($this->path), true);

        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_map(fn (array $item): DiscoveryResult => DiscoveryResult::fromArray($item, 'json'), $payload));
        }

        $items = $payload['items'] ?? $payload['rows'] ?? [$payload];

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(fn (array $item): DiscoveryResult => DiscoveryResult::fromArray($item, 'json'), $items));
    }
}
