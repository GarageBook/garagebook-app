<?php

namespace App\Support;

class Analytics
{
    public static function ga4Enabled(): bool
    {
        return app()->environment('production') && filled(self::ga4MeasurementId());
    }

    public static function ga4MeasurementId(): ?string
    {
        $measurementId = config('analytics.ga4.measurement_id');

        return is_string($measurementId) && filled($measurementId)
            ? $measurementId
            : null;
    }

    public static function ga4LinkerDomains(): array
    {
        $domains = config('analytics.ga4.linker_domains', []);

        if (! is_array($domains)) {
            return [];
        }

        return array_values(array_filter(
            $domains,
            fn (mixed $domain): bool => is_string($domain) && filled($domain)
        ));
    }
}
