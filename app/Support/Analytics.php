<?php

namespace App\Support;

use App\Models\User;

class Analytics
{
    public static function ga4Enabled(): bool
    {
        return app()->environment('production') && filled(self::ga4MeasurementId());
    }

    public static function frontendTrackingEnabled(): bool
    {
        return self::ga4Enabled() || app()->environment('local');
    }

    public static function frontendDebugEnabled(): bool
    {
        return app()->environment('local');
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

    public static function anonymizeIdentifier(string $scope, int|string|null $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        return hash_hmac('sha256', "{$scope}:{$identifier}", $key);
    }

    public static function userState(?User $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $vehicleCount = $user->vehicles()->count();
        $maintenanceCount = $user->vehicles()->withCount('maintenanceLogs')->get()->sum('maintenance_logs_count');
        $documentCount = $user->vehicles()->withCount('documents')->get()->sum('documents_count');
        $fuelLogCount = $user->vehicles()->withCount('fuelLogs')->get()->sum('fuel_logs_count');

        if ($vehicleCount === 0 && $maintenanceCount === 0 && $documentCount === 0 && $fuelLogCount === 0) {
            return 'new';
        }

        if ($user->first_login_at && $user->last_login_at && $user->last_login_at->gt($user->first_login_at)) {
            return 'returning';
        }

        return 'active';
    }

    public static function clickTrackingAttributes(string $eventName, array $params = []): array
    {
        $attributes = [
            'data-analytics-click' => 'true',
            'data-analytics-event' => $eventName,
        ];

        foreach (self::sanitizeParams($params) as $key => $value) {
            $attributes['data-analytics-param-' . str_replace('_', '-', $key)] = (string) $value;
        }

        return $attributes;
    }

    public static function sanitizeParams(array $params): array
    {
        return array_filter(
            $params,
            fn (mixed $value): bool => is_string($value) || is_int($value) || is_float($value) || is_bool($value)
        );
    }
}
