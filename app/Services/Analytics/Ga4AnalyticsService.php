<?php

namespace App\Services\Analytics;

use Carbon\CarbonInterface;
use RuntimeException;

class Ga4AnalyticsService extends GoogleApiService
{
    protected function configPrefix(): string
    {
        return 'google_analytics';
    }

    protected function scopes(): array
    {
        return ['https://www.googleapis.com/auth/analytics.readonly'];
    }

    public function isConfigured(): bool
    {
        return parent::isConfigured() && filled($this->propertyId());
    }

    public function fetchDailySummary(CarbonInterface $date): ?array
    {
        $response = $this->runReport($date, [
            'dimensions' => [
                ['name' => 'date'],
            ],
            'metrics' => [
                ['name' => 'totalUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
                ['name' => 'eventCount'],
                ['name' => 'conversions'],
            ],
        ]);

        $row = $response['rows'][0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        $metrics = $row['metricValues'] ?? [];

        return [
            'date' => $date->toDateString(),
            'users' => $this->metricInteger($metrics, 0),
            'sessions' => $this->metricInteger($metrics, 1),
            'screen_page_views' => $this->metricInteger($metrics, 2),
            'event_count' => $this->metricInteger($metrics, 3),
            'conversions' => $this->metricNullableInteger($metrics, 4),
        ];
    }

    /**
     * @return list<array{date:string,page_path:string,page_title:?string,views:int,users:?int}>
     */
    public function fetchTopPages(CarbonInterface $date, int $limit = 10): array
    {
        $response = $this->runReport($date, [
            'dimensions' => [
                ['name' => 'pagePath'],
                ['name' => 'pageTitle'],
            ],
            'metrics' => [
                ['name' => 'screenPageViews'],
                ['name' => 'totalUsers'],
            ],
            'orderBys' => [
                [
                    'metric' => ['metricName' => 'screenPageViews'],
                    'desc' => true,
                ],
            ],
            'limit' => (string) $limit,
        ]);

        $rows = $response['rows'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->map(function (mixed $row) use ($date): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $dimensions = $row['dimensionValues'] ?? [];
                $metrics = $row['metricValues'] ?? [];
                $pagePath = $dimensions[0]['value'] ?? null;

                if (! is_string($pagePath) || $pagePath === '') {
                    return null;
                }

                $pageTitle = $dimensions[1]['value'] ?? null;

                return [
                    'date' => $date->toDateString(),
                    'page_path' => $pagePath,
                    'page_title' => is_string($pageTitle) && $pageTitle !== '(not set)' ? $pageTitle : null,
                    'views' => $this->metricInteger($metrics, 0),
                    'users' => $this->metricNullableInteger($metrics, 1),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function propertyId(): ?string
    {
        $propertyId = config('services.google_analytics.property_id');

        return is_string($propertyId) && trim($propertyId) !== ''
            ? trim($propertyId)
            : null;
    }

    private function runReport(CarbonInterface $date, array $payload): array
    {
        $propertyId = $this->propertyId();

        if ($propertyId === null) {
            throw new RuntimeException('GOOGLE_ANALYTICS_PROPERTY_ID ontbreekt.');
        }

        return $this->postJson(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport",
            array_merge([
                'dateRanges' => [[
                    'startDate' => $date->toDateString(),
                    'endDate' => $date->toDateString(),
                ]],
            ], $payload),
        );
    }

    private function metricInteger(array $metrics, int $index): int
    {
        return (int) round((float) ($metrics[$index]['value'] ?? 0));
    }

    private function metricNullableInteger(array $metrics, int $index): ?int
    {
        $value = $metrics[$index]['value'] ?? null;

        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
