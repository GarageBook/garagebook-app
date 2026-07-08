<?php

namespace App\Services\Gsc;

use App\Models\GscCountrySnapshot;
use App\Models\GscDateSnapshot;
use App\Models\GscDeviceSnapshot;
use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use App\Models\GscSearchAppearanceSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SearchConsoleInsightsService
{
    public function __construct(
        private readonly SeoOpportunityService $opportunityService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(array $opportunityFilters = []): array
    {
        $latestDate = $this->latestDate();
        $previousDate = $latestDate ? $this->previousDate($latestDate) : null;

        if ($latestDate === null) {
            return [
                'latest_date' => null,
                'previous_date' => null,
                'summary' => $this->emptySummary(),
                'quick_wins' => [],
                'low_ctr' => [],
                'new_queries' => [],
                'winners' => [],
                'losers' => [],
                'vehicle_authority' => [
                    'top_pages' => [],
                    'zero_click_pages' => [],
                    'average_position' => null,
                ],
                'priorities' => [],
                'dimensions' => $this->emptyDimensions(),
                'opportunities' => [],
                'opportunity_types' => $this->opportunityService->types(),
                'opportunity_page_types' => $this->opportunityService->pageTypes(),
            ];
        }

        $pages = GscPageSnapshot::query()->whereDate('date', $latestDate)->get();
        $queries = GscQuerySnapshot::query()->whereDate('date', $latestDate)->get();

        return [
            'latest_date' => $latestDate,
            'previous_date' => $previousDate,
            'summary' => $this->summary($pages, $queries),
            'quick_wins' => $this->quickWins($pages),
            'low_ctr' => $this->lowCtr($pages),
            'new_queries' => $this->newQueries($latestDate, $previousDate),
            'winners' => $this->positionChanges($latestDate, $previousDate, true),
            'losers' => $this->positionChanges($latestDate, $previousDate, false),
            'vehicle_authority' => $this->vehicleAuthority($pages),
            'priorities' => $this->priorities($pages, $queries),
            'dimensions' => $this->dimensions($latestDate),
            'opportunities' => $this->opportunityService->top($opportunityFilters),
            'opportunity_types' => $this->opportunityService->types(),
            'opportunity_page_types' => $this->opportunityService->pageTypes(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(): array
    {
        $dashboard = $this->dashboard();

        return [
            ...$this->sectionRows('quick_wins', $dashboard['quick_wins']),
            ...$this->sectionRows('low_ctr', $dashboard['low_ctr']),
            ...$this->sectionRows('winners', $dashboard['winners']),
            ...$this->sectionRows('losers', $dashboard['losers']),
            ...$this->sectionRows('vehicle_authority_top_pages', $dashboard['vehicle_authority']['top_pages'] ?? []),
            ...$this->sectionRows('vehicle_authority_zero_click_pages', $dashboard['vehicle_authority']['zero_click_pages'] ?? []),
        ];
    }

    private function latestDate(): ?string
    {
        $date = collect([
            GscPageSnapshot::query()->max('date'),
            GscQuerySnapshot::query()->max('date'),
            GscCountrySnapshot::query()->max('date'),
            GscDeviceSnapshot::query()->max('date'),
            GscSearchAppearanceSnapshot::query()->max('date'),
            GscDateSnapshot::query()->max('date'),
        ])->filter()->max();

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    private function previousDate(string $latestDate): ?string
    {
        $pageDate = GscPageSnapshot::query()
            ->whereDate('date', '<', $latestDate)
            ->max('date');
        $queryDate = GscQuerySnapshot::query()
            ->whereDate('date', '<', $latestDate)
            ->max('date');

        $date = collect([$pageDate, $queryDate])->filter()->max();

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * @param  Collection<int, GscPageSnapshot>  $pages
     * @param  Collection<int, GscQuerySnapshot>  $queries
     * @return array<string, mixed>
     */
    private function summary(Collection $pages, Collection $queries): array
    {
        $clicks = $pages->sum('clicks');
        $impressions = $pages->sum('impressions');

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
            'position' => round((float) $pages->avg('position'), 2),
            'pages' => $pages->count(),
            'queries' => $queries->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDimensions(): array
    {
        return [
            'devices' => [],
            'countries' => [],
            'search_appearances' => [],
            'dates' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dimensions(string $latestDate): array
    {
        return [
            'devices' => GscDeviceSnapshot::query()
                ->whereDate('date', $latestDate)
                ->orderByDesc('impressions')
                ->get()
                ->map(fn (GscDeviceSnapshot $row): array => $this->dimensionRow($row, 'device'))
                ->all(),
            'countries' => GscCountrySnapshot::query()
                ->whereDate('date', $latestDate)
                ->orderByDesc('impressions')
                ->get()
                ->map(fn (GscCountrySnapshot $row): array => $this->dimensionRow($row, 'country'))
                ->all(),
            'search_appearances' => GscSearchAppearanceSnapshot::query()
                ->whereDate('date', $latestDate)
                ->orderByDesc('impressions')
                ->get()
                ->map(fn (GscSearchAppearanceSnapshot $row): array => $this->dimensionRow($row, 'appearance'))
                ->all(),
            'dates' => GscDateSnapshot::query()
                ->whereDate('date', $latestDate)
                ->orderBy('data_date')
                ->get()
                ->map(fn (GscDateSnapshot $row): array => [
                    'date' => $row->data_date?->toDateString(),
                    'clicks' => $row->clicks,
                    'impressions' => $row->impressions,
                    'ctr' => (float) $row->ctr,
                    'position' => $row->position !== null ? (float) $row->position : null,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dimensionRow(object $row, string $labelColumn): array
    {
        return [
            'label' => $row->{$labelColumn},
            'clicks' => $row->clicks,
            'impressions' => $row->impressions,
            'ctr' => (float) $row->ctr,
            'position' => $row->position !== null ? (float) $row->position : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'position' => 0,
            'pages' => 0,
            'queries' => 0,
        ];
    }

    /**
     * @param  Collection<int, GscPageSnapshot>  $pages
     * @return list<array<string, mixed>>
     */
    private function quickWins(Collection $pages): array
    {
        return $pages
            ->filter(fn (GscPageSnapshot $page): bool => (float) $page->position >= 10
                && (float) $page->position <= 20
                && $page->impressions >= 20)
            ->sortByDesc('impressions')
            ->take(20)
            ->map(fn (GscPageSnapshot $page): array => $this->pageRow($page))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, GscPageSnapshot>  $pages
     * @return list<array<string, mixed>>
     */
    private function lowCtr(Collection $pages): array
    {
        return $pages
            ->filter(fn (GscPageSnapshot $page): bool => $page->impressions >= 50 && (float) $page->ctr < 0.02)
            ->sortByDesc('impressions')
            ->take(20)
            ->map(fn (GscPageSnapshot $page): array => $this->pageRow($page))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newQueries(string $latestDate, ?string $previousDate): array
    {
        if ($previousDate === null) {
            return [];
        }

        $previousQueries = GscQuerySnapshot::query()
            ->whereDate('date', $previousDate)
            ->pluck('query')
            ->map(fn (string $query): string => mb_strtolower($query))
            ->flip();

        return GscQuerySnapshot::query()
            ->whereDate('date', $latestDate)
            ->orderByDesc('impressions')
            ->get()
            ->reject(fn (GscQuerySnapshot $query): bool => $previousQueries->has(mb_strtolower($query->query)))
            ->take(20)
            ->map(fn (GscQuerySnapshot $query): array => $this->queryRow($query))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function positionChanges(string $latestDate, ?string $previousDate, bool $winners): array
    {
        if ($previousDate === null) {
            return [];
        }

        $previous = GscQuerySnapshot::query()
            ->whereDate('date', $previousDate)
            ->get()
            ->keyBy(fn (GscQuerySnapshot $query): string => $this->queryComparisonKey($query));

        return GscQuerySnapshot::query()
            ->whereDate('date', $latestDate)
            ->get()
            ->map(function (GscQuerySnapshot $query) use ($previous): ?array {
                $previousQuery = $previous->get($this->queryComparisonKey($query));

                if (! $previousQuery || $query->position === null || $previousQuery->position === null) {
                    return null;
                }

                $row = $this->queryRow($query);
                $row['previous_position'] = (float) $previousQuery->position;
                $row['position_delta'] = round((float) $previousQuery->position - (float) $query->position, 2);

                return $row;
            })
            ->filter(fn (?array $row): bool => $row !== null && ($winners ? $row['position_delta'] > 0 : $row['position_delta'] < 0))
            ->sortBy(fn (array $row): float => $winners ? -$row['position_delta'] : $row['position_delta'])
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, GscPageSnapshot>  $pages
     * @return array<string, mixed>
     */
    private function vehicleAuthority(Collection $pages): array
    {
        $vehicleAuthorityPages = $pages->where('page_type', 'vehicle_authority');

        return [
            'top_pages' => $vehicleAuthorityPages
                ->sortByDesc('clicks')
                ->take(10)
                ->map(fn (GscPageSnapshot $page): array => $this->pageRow($page))
                ->values()
                ->all(),
            'zero_click_pages' => $vehicleAuthorityPages
                ->filter(fn (GscPageSnapshot $page): bool => $page->impressions > 0 && $page->clicks === 0)
                ->sortByDesc('impressions')
                ->take(10)
                ->map(fn (GscPageSnapshot $page): array => $this->pageRow($page))
                ->values()
                ->all(),
            'average_position' => $vehicleAuthorityPages->isNotEmpty()
                ? round((float) $vehicleAuthorityPages->avg('position'), 2)
                : null,
        ];
    }

    /**
     * @param  Collection<int, GscPageSnapshot>  $pages
     * @param  Collection<int, GscQuerySnapshot>  $queries
     * @return list<array<string, mixed>>
     */
    private function priorities(Collection $pages, Collection $queries): array
    {
        $priorities = [];

        foreach ($this->lowCtr($pages) as $row) {
            $priorities[] = $row + ['recommendation' => 'Verbeter title/meta description.'];
        }

        foreach ($this->quickWins($pages) as $row) {
            $priorities[] = $row + ['recommendation' => 'Breid content uit en voeg interne links toe.'];
        }

        foreach ($this->vehicleAuthority($pages)['zero_click_pages'] as $row) {
            $priorities[] = $row + ['recommendation' => 'Controleer H1/title en voeg model-specifieke data toe.'];
        }

        foreach ($queries->whereNull('path')->where('impressions', '>=', 20)->sortByDesc('impressions')->take(10) as $query) {
            $priorities[] = $this->queryRow($query) + ['recommendation' => 'Maak of verbeter landingspagina.'];
        }

        return collect($priorities)
            ->unique(fn (array $row): string => ($row['path'] ?? $row['query'] ?? '').'|'.$row['recommendation'])
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pageRow(GscPageSnapshot $page): array
    {
        return [
            'page_url' => $page->page_url,
            'path' => $page->path,
            'clicks' => $page->clicks,
            'impressions' => $page->impressions,
            'ctr' => (float) $page->ctr,
            'position' => $page->position !== null ? (float) $page->position : null,
            'page_type' => $page->page_type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queryRow(GscQuerySnapshot $query): array
    {
        return [
            'query' => $query->query,
            'page_url' => $query->page_url,
            'path' => $query->path,
            'clicks' => $query->clicks,
            'impressions' => $query->impressions,
            'ctr' => (float) $query->ctr,
            'position' => $query->position !== null ? (float) $query->position : null,
            'page_type' => $query->page_type,
        ];
    }

    private function queryComparisonKey(GscQuerySnapshot $query): string
    {
        return mb_strtolower($query->query).'|'.($query->path ?? '');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sectionRows(string $section, array $rows): array
    {
        return array_map(fn (array $row): array => ['section' => $section] + $row, $rows);
    }
}
