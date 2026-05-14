<?php

namespace App\Services\Analytics;

use Carbon\CarbonInterface;
use RuntimeException;

class SearchConsoleService extends GoogleApiService
{
    protected function configPrefix(): string
    {
        return 'search_console';
    }

    protected function scopes(): array
    {
        return ['https://www.googleapis.com/auth/webmasters.readonly'];
    }

    public function isConfigured(): bool
    {
        return parent::isConfigured() && filled($this->siteUrl());
    }

    public function fetchDailySummary(CarbonInterface $date): ?array
    {
        $response = $this->query($date, []);
        $row = $response['rows'][0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        return [
            'date' => $date->toDateString(),
            'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
            'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
            'ctr' => isset($row['ctr']) ? round((float) $row['ctr'], 4) : null,
            'position' => isset($row['position']) ? round((float) $row['position'], 2) : null,
        ];
    }

    /**
     * @return list<array{date:string,query:string,clicks:int,impressions:int,ctr:?float,position:?float}>
     */
    public function fetchTopQueries(CarbonInterface $date, int $limit = 10): array
    {
        return $this->mapRows(
            $date,
            $this->query($date, [
                'dimensions' => ['query'],
                'rowLimit' => $limit,
            ]),
            'query'
        );
    }

    /**
     * @return list<array{date:string,page:string,clicks:int,impressions:int,ctr:?float,position:?float}>
     */
    public function fetchTopPages(CarbonInterface $date, int $limit = 10): array
    {
        return $this->mapRows(
            $date,
            $this->query($date, [
                'dimensions' => ['page'],
                'rowLimit' => $limit,
            ]),
            'page'
        );
    }

    private function siteUrl(): ?string
    {
        $siteUrl = config('services.search_console.site_url');

        return is_string($siteUrl) && trim($siteUrl) !== ''
            ? trim($siteUrl)
            : null;
    }

    private function query(CarbonInterface $date, array $payload): array
    {
        $siteUrl = $this->siteUrl();

        if ($siteUrl === null) {
            throw new RuntimeException('GOOGLE_SEARCH_CONSOLE_SITE_URL ontbreekt.');
        }

        return $this->postJson(
            'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode($siteUrl) . '/searchAnalytics/query',
            array_merge([
                'startDate' => $date->toDateString(),
                'endDate' => $date->toDateString(),
            ], $payload),
        );
    }

    /**
     * @return list<array<string, int|float|string|null>>
     */
    private function mapRows(CarbonInterface $date, array $response, string $dimensionKey): array
    {
        $rows = $response['rows'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->map(function (mixed $row) use ($date, $dimensionKey): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $value = $row['keys'][0] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    return null;
                }

                return [
                    'date' => $date->toDateString(),
                    $dimensionKey => $value,
                    'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                    'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                    'ctr' => isset($row['ctr']) ? round((float) $row['ctr'], 4) : null,
                    'position' => isset($row['position']) ? round((float) $row['position'], 2) : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
