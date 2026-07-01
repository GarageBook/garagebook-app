<?php

namespace App\Services\Growth\Partner2026;

use App\Services\Growth\Community2026\CommunityDiscoveryProvider;

abstract class AbstractPartnerDiscoveryProvider implements CommunityDiscoveryProvider
{
    /**
     * @param  array<int, string>  $domains
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    protected function urlsForDomains(array $domains, array $paths = ['', '/contact', '/over-ons', '/privacy', '/algemene-voorwaarden']): array
    {
        $urls = [];

        foreach ($domains as $domain) {
            $domain = trim((string) $domain);

            if ($domain === '') {
                continue;
            }

            $base = str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
                ? rtrim($domain, '/')
                : 'https://'.ltrim($domain, '/');

            foreach ($paths as $path) {
                $path = trim((string) $path);

                if ($path === '') {
                    $urls[] = $base;

                    continue;
                }

                $urls[] = rtrim($base, '/').'/'.ltrim($path, '/');
            }
        }

        return array_values(array_unique($urls));
    }
}
