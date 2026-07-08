<?php

namespace App\Services\Gsc;

use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use App\Models\SeoOpportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SeoOpportunityService
{
    public const TYPE_LOW_CTR = 'high_impressions_low_ctr';

    public const TYPE_POSITION_8_20 = 'position_8_20';

    public const TYPE_NEW_KEYWORD = 'new_keyword_no_page';

    public const TYPE_VEHICLE_AUTHORITY = 'vehicle_authority_zero_clicks';

    public const TYPE_RISING = 'strong_riser';

    public const TYPE_DECLINING = 'strong_decliner';

    /**
     * @return list<array<string, string>>
     */
    public function types(): array
    {
        return [
            ['value' => self::TYPE_LOW_CTR, 'label' => 'Veel impressies, weinig klikken'],
            ['value' => self::TYPE_POSITION_8_20, 'label' => 'Positie 8-20'],
            ['value' => self::TYPE_NEW_KEYWORD, 'label' => 'Nieuwe zoekwoorden'],
            ['value' => self::TYPE_VEHICLE_AUTHORITY, 'label' => 'Vehicle Authority'],
            ['value' => self::TYPE_RISING, 'label' => 'Sterke stijgers'],
            ['value' => self::TYPE_DECLINING, 'label' => 'Sterke dalers'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function pageTypes(): array
    {
        return collect(['homepage', 'garage_page', 'vehicle_authority', 'seo_page', 'static_page', 'other'])
            ->map(fn (string $type): array => ['value' => $type, 'label' => $type])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function top(array $filters = [], int $limit = 20): array
    {
        $latestDate = $this->latestDate();

        if ($latestDate === null) {
            return [];
        }

        $this->refreshForDate($latestDate);

        return $this->query($filters + ['date' => $latestDate])
            ->limit($limit)
            ->get()
            ->map(fn (SeoOpportunity $opportunity): array => $this->row($opportunity))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters = []): array
    {
        $latestDate = $this->latestDate();

        if ($latestDate === null) {
            return [];
        }

        $this->refreshForDate($latestDate);

        return $this->query($filters + ['date' => $latestDate])
            ->get()
            ->map(fn (SeoOpportunity $opportunity): array => $this->row($opportunity))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function refreshLatest(): array
    {
        $latestDate = $this->latestDate();

        if ($latestDate === null) {
            return [];
        }

        return $this->refreshForDate($latestDate);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function refreshForDate(string $date): array
    {
        $date = Carbon::parse($date)->toDateString();
        $previousDate = $this->previousDate($date);
        $opportunities = $this->generate($date, $previousDate);

        SeoOpportunity::query()->whereDate('date', $date)->delete();

        foreach ($opportunities as $opportunity) {
            SeoOpportunity::query()->create($opportunity);
        }

        return $opportunities;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function weeklyTop(int $limit = 10): array
    {
        return array_slice($this->top([], $limit), 0, $limit);
    }

    public function latestDate(): ?string
    {
        $date = collect([
            GscPageSnapshot::query()->max('date'),
            GscQuerySnapshot::query()->max('date'),
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
     * @return list<array<string, mixed>>
     */
    private function generate(string $date, ?string $previousDate): array
    {
        $pages = GscPageSnapshot::query()->whereDate('date', $date)->get();
        $queries = GscQuerySnapshot::query()->whereDate('date', $date)->get();
        $changes = $previousDate ? $this->positionChanges($date, $previousDate) : collect();
        $previousQueries = $previousDate
            ? GscQuerySnapshot::query()->whereDate('date', $previousDate)->pluck('query')->map(fn (string $query): string => mb_strtolower($query))->flip()
            : collect();

        $opportunities = [];

        foreach ($pages as $page) {
            $ctr = (float) $page->ctr;
            $position = $page->position !== null ? (float) $page->position : null;

            if ($page->impressions > 100 && $ctr < 0.02) {
                $opportunities[] = $this->pageOpportunity(
                    $date,
                    self::TYPE_LOW_CTR,
                    'Veel impressies, weinig klikken',
                    'Deze pagina krijgt veel vertoningen maar relatief weinig clicks.',
                    $page,
                    'Verbeter title en meta description.',
                    'Low',
                    $this->impactScore($page->impressions, $ctr, $position),
                );
            }

            if ($position !== null && $position >= 8 && $position <= 20) {
                $opportunities[] = $this->pageOpportunity(
                    $date,
                    self::TYPE_POSITION_8_20,
                    'Pagina staat op positie 8-20',
                    'Deze pagina staat dicht bij pagina 1 of net binnen de top 10 en kan met content/interne links stijgen.',
                    $page,
                    'Breid content uit en voeg interne links toe.',
                    'Medium',
                    $this->impactScore($page->impressions, $ctr, $position, 12),
                );
            }

            if ($page->page_type === 'vehicle_authority' && $page->impressions > 50 && $page->clicks === 0) {
                $opportunities[] = $this->pageOpportunity(
                    $date,
                    self::TYPE_VEHICLE_AUTHORITY,
                    'Vehicle Authority pagina met impressies maar geen clicks',
                    'Deze modelpagina wordt gevonden maar trekt nog geen bezoekers.',
                    $page,
                    'Modelpagina uitbreiden.',
                    'Medium',
                    $this->impactScore($page->impressions, $ctr, $position, 18),
                );
            }
        }

        foreach ($queries as $query) {
            if (! $previousQueries->has(mb_strtolower($query->query)) && $query->impressions >= 50 && blank($query->path)) {
                $opportunities[] = $this->queryOpportunity(
                    $date,
                    self::TYPE_NEW_KEYWORD,
                    'Nieuw zoekwoord zonder passende pagina',
                    'Deze nieuwe query heeft veel impressies maar nog geen duidelijke landingspagina.',
                    $query,
                    'Nieuwe SEO-landingspagina maken.',
                    'High',
                    $this->impactScore($query->impressions, (float) $query->ctr, $query->position !== null ? (float) $query->position : null, 20),
                );
            }
        }

        foreach ($changes as $change) {
            if ($change['position_delta'] >= 5) {
                $opportunities[] = $this->changeOpportunity(
                    $date,
                    self::TYPE_RISING,
                    'Sterke stijger',
                    'Deze query stijgt duidelijk in positie.',
                    $change,
                    'Blijven monitoren.',
                    'Low',
                    $this->impactScore($change['impressions'], (float) $change['ctr'], $change['position'], 8),
                );
            }

            if ($change['position_delta'] <= -5) {
                $opportunities[] = $this->changeOpportunity(
                    $date,
                    self::TYPE_DECLINING,
                    'Sterke daler',
                    'Deze query verliest duidelijk positie.',
                    $change,
                    'Direct onderzoeken.',
                    'Medium',
                    $this->impactScore($change['impressions'], (float) $change['ctr'], $change['position'], 22),
                );
            }
        }

        return collect($opportunities)
            ->unique(fn (array $opportunity): string => $opportunity['date'].'|'.$opportunity['type'].'|'.($opportunity['path'] ?? '').'|'.($opportunity['query'] ?? ''))
            ->sortByDesc('impact_score')
            ->values()
            ->all();
    }

    private function impactScore(int $impressions, float $ctr, ?float $position, int $bonus = 0): int
    {
        $impressionScore = min(45, (int) round($impressions / 10));
        $ctrScore = $ctr < 0.02 ? (int) round(((0.02 - $ctr) / 0.02) * 25) : 0;
        $positionScore = $position !== null && $position >= 8 && $position <= 20
            ? (int) round(25 - (($position - 8) / 12) * 10)
            : 0;

        return max(0, min(100, $impressionScore + $ctrScore + $positionScore + $bonus));
    }

    private function priority(int $score): string
    {
        return match (true) {
            $score >= 80 => 'High',
            $score >= 50 => 'Medium',
            default => 'Low',
        };
    }

    private function pageOpportunity(
        string $date,
        string $type,
        string $title,
        string $description,
        GscPageSnapshot $page,
        string $action,
        string $effort,
        int $score,
    ): array {
        return [
            'date' => $date,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'impact_score' => $score,
            'effort' => $effort,
            'priority' => $this->priority($score),
            'page_url' => $page->page_url,
            'path' => $page->path,
            'query' => null,
            'page_type' => $page->page_type,
            'brand' => $this->brandFromPath($page->path),
            'recommended_action' => $action,
            'metadata' => $this->metadata($page->impressions, $page->clicks, (float) $page->ctr, $page->position !== null ? (float) $page->position : null),
        ];
    }

    private function queryOpportunity(
        string $date,
        string $type,
        string $title,
        string $description,
        GscQuerySnapshot $query,
        string $action,
        string $effort,
        int $score,
    ): array {
        return [
            'date' => $date,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'impact_score' => $score,
            'effort' => $effort,
            'priority' => $this->priority($score),
            'page_url' => $query->page_url,
            'path' => $query->path,
            'query' => $query->query,
            'page_type' => $query->page_type,
            'brand' => $this->brandFromPath($query->path),
            'recommended_action' => $action,
            'metadata' => $this->metadata($query->impressions, $query->clicks, (float) $query->ctr, $query->position !== null ? (float) $query->position : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $change
     */
    private function changeOpportunity(
        string $date,
        string $type,
        string $title,
        string $description,
        array $change,
        string $action,
        string $effort,
        int $score,
    ): array {
        return [
            'date' => $date,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'impact_score' => $score,
            'effort' => $effort,
            'priority' => $this->priority($score),
            'page_url' => $change['page_url'],
            'path' => $change['path'],
            'query' => $change['query'],
            'page_type' => $change['page_type'],
            'brand' => $this->brandFromPath($change['path']),
            'recommended_action' => $action,
            'metadata' => $this->metadata($change['impressions'], $change['clicks'], (float) $change['ctr'], $change['position'], [
                'previous_position' => $change['previous_position'],
                'position_delta' => $change['position_delta'],
            ]),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function positionChanges(string $latestDate, string $previousDate): Collection
    {
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

                return [
                    'query' => $query->query,
                    'page_url' => $query->page_url,
                    'path' => $query->path,
                    'clicks' => $query->clicks,
                    'impressions' => $query->impressions,
                    'ctr' => (float) $query->ctr,
                    'position' => (float) $query->position,
                    'previous_position' => (float) $previousQuery->position,
                    'position_delta' => round((float) $previousQuery->position - (float) $query->position, 2),
                    'page_type' => $query->page_type,
                ];
            })
            ->filter()
            ->values();
    }

    private function queryComparisonKey(GscQuerySnapshot $query): string
    {
        return mb_strtolower($query->query).'|'.($query->path ?? '');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function metadata(int $impressions, int $clicks, float $ctr, ?float $position, array $extra = []): array
    {
        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $ctr,
            'position' => $position,
            'score_formula' => 'min(45, impressions/10) + low-CTR bonus up to 25 + position 8-20 bonus up to 25 + type bonus',
        ] + $extra;
    }

    private function brandFromPath(?string $path): ?string
    {
        if (! $path || ! str_starts_with($path, '/onderhoud/')) {
            return null;
        }

        $segments = explode('/', trim($path, '/'));

        return $segments[1] ?? null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<SeoOpportunity>
     */
    private function query(array $filters): Builder
    {
        return SeoOpportunity::query()
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('date', Carbon::parse($date)->toDateString()))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['page_type'] ?? null, fn (Builder $query, string $pageType) => $query->where('page_type', $pageType))
            ->when($filters['brand'] ?? null, fn (Builder $query, string $brand) => $query->where('brand', mb_strtolower($brand)))
            ->when($filters['min_score'] ?? null, fn (Builder $query, string|int $score) => $query->where('impact_score', '>=', (int) $score))
            ->orderByDesc('impact_score')
            ->orderBy('type')
            ->orderBy('path')
            ->orderBy('query');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(SeoOpportunity $opportunity): array
    {
        return [
            'date' => $opportunity->date?->toDateString(),
            'type' => $opportunity->type,
            'title' => $opportunity->title,
            'description' => $opportunity->description,
            'impact_score' => $opportunity->impact_score,
            'effort' => $opportunity->effort,
            'priority' => $opportunity->priority,
            'page_url' => $opportunity->page_url,
            'path' => $opportunity->path,
            'query' => $opportunity->query,
            'page_type' => $opportunity->page_type,
            'brand' => $opportunity->brand,
            'recommended_action' => $opportunity->recommended_action,
            'metadata' => $opportunity->metadata ?? [],
        ];
    }
}
