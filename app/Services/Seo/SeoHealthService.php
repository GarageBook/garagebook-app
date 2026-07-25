<?php

namespace App\Services\Seo;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Support\PublicSeoUrl;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SeoHealthService
{
    public function __construct(
        private readonly PublicGarageService $publicGarageService,
        private readonly GarageQualityScore $qualityScore,
    ) {}

    public function report(): array
    {
        $vehicles = $this->publicVehicles();
        $allVehicles = $this->allVehicles();
        $sitemapVehicles = $this->publicGarageService->indexableVehicles();
        $eligibleUrls = $this->eligibleUrls($sitemapVehicles);
        $sitemapUrls = $this->sitemapGarageUrls($sitemapVehicles);
        $sitemapUrlCounts = $sitemapUrls->countBy();

        $garageInspections = $this->inspectGarageVehicles($vehicles);
        $canonicalCounts = collect($garageInspections['canonical'])->filter()->countBy();
        $duplicateCanonicals = $canonicalCounts->filter(fn (int $count): bool => $count > 1);
        $sitemapNotEligible = $sitemapUrls->diff($eligibleUrls)->values();
        $eligibleMissingFromSitemap = $eligibleUrls->diff($sitemapUrls)->values();
        $sitemapInspections = $this->inspectSitemapUrls($sitemapUrls, $vehicles);

        $publicWithSlugButNoindex = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug) && ! $this->publicGarageService->shouldIndex($vehicle))
            ->values();
        $indexableVehicles = $vehicles->filter(fn (Vehicle $vehicle): bool => $this->publicGarageService->shouldIndex($vehicle))->values();
        $qualityByVehicle = $this->qualityScores($indexableVehicles);
        $actionItems = $this->actionItems(
            $vehicles,
            $eligibleUrls,
            $sitemapUrls,
            $garageInspections,
            $qualityByVehicle,
        );

        $structuredDataErrorUrls = collect([
            ...$garageInspections['webpage_schema_missing_urls'],
            ...$garageInspections['vehicle_schema_missing_urls'],
            ...$garageInspections['product_schema_urls'],
        ])->unique()->values();

        $critical = [
            'noindex_urls_in_sitemap' => count($sitemapInspections['noindex_urls']),
            'demo_outreach_urls_in_sitemap' => count($sitemapInspections['demo_outreach_urls']),
            'sitemap_not_eligible' => $sitemapNotEligible->count(),
            'sitemap_redirects' => count($sitemapInspections['redirect_urls']),
            'sitemap_404s' => count($sitemapInspections['not_found_urls']),
            'redirect_chains' => count($garageInspections['redirect_chain_urls']),
            'public_pages_without_slug' => $vehicles->filter(fn (Vehicle $vehicle): bool => blank($vehicle->public_slug))->count(),
        ];

        $warnings = [
            'eligible_missing_from_sitemap' => $eligibleMissingFromSitemap->count(),
            'canonical_mismatches' => count($garageInspections['canonical_mismatch_urls']),
            'host_mismatches' => count($garageInspections['canonical_host_mismatch_urls']),
            'querystring_issues' => count($garageInspections['querystring_issue_urls']),
            'structured_data_errors' => $structuredDataErrorUrls->count(),
            'unexpected_noindex' => $publicWithSlugButNoindex
                ->reject(fn (Vehicle $vehicle): bool => $this->publicGarageService->isOutreachDemoVehicle($vehicle))
                ->count(),
        ];

        $opportunities = $this->opportunityCounts($actionItems);
        $informational = [
            'total_vehicles' => $allVehicles->count(),
            'vehicles_with_owner' => $allVehicles->filter(fn (Vehicle $vehicle): bool => filled($vehicle->user_id))->count(),
            'public_vehicles' => $vehicles->count(),
            'hidden_vehicles' => $allVehicles->filter(fn (Vehicle $vehicle): bool => ! (bool) $vehicle->is_public)->count(),
            'demo_outreach_vehicles' => $allVehicles->filter(fn (Vehicle $vehicle): bool => $this->publicGarageService->isOutreachDemoVehicle($vehicle))->count(),
            'vehicles_without_photo' => $allVehicles->filter(fn (Vehicle $vehicle): bool => $this->publicGarageService->publicVehiclePhotos($vehicle) === [])->count(),
            'vehicles_without_maintenance' => $allVehicles->filter(fn (Vehicle $vehicle): bool => $vehicle->maintenanceLogs->isEmpty())->count(),
        ];

        $productMetrics = [
            ...$informational,
            'vehicles_with_public_slug' => $vehicles->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))->count(),
        ];

        $indexabilityMetrics = [
            'indexable_public_garages' => $indexableVehicles->count(),
            'public_noindex_pages' => $publicWithSlugButNoindex->count(),
            'sitemap_eligible' => $eligibleUrls->count(),
            'included_in_sitemap' => $sitemapUrls->count(),
            'eligible_missing_from_sitemap' => $eligibleMissingFromSitemap->count(),
            'noindex_in_sitemap' => count($sitemapInspections['noindex_urls']),
            'demo_outreach_indexable' => $vehicles
                ->filter(fn (Vehicle $vehicle): bool => $this->publicGarageService->isOutreachDemoVehicle($vehicle) && $this->publicGarageService->shouldIndex($vehicle))
                ->count(),
            'canonical_mismatches' => count($garageInspections['canonical_mismatch_urls']),
            'host_mismatches' => count($garageInspections['canonical_host_mismatch_urls']),
            'structured_data_errors' => $structuredDataErrorUrls->count(),
        ];

        $contentQualityMetrics = $this->contentQualityMetrics($indexableVehicles, $qualityByVehicle, $actionItems);

        return [
            'status' => array_sum($critical) > 0 ? 'fail' : (array_sum($warnings) > 0 ? 'warning' : 'pass'),
            'critical_errors' => array_sum($critical),
            'warnings' => array_sum($warnings),
            'opportunity_count' => array_sum($opportunities),
            'critical' => $critical,
            'warning_counts' => $warnings,
            'opportunity_counts' => $opportunities,
            'informational_counts' => $informational,
            'product_metrics' => $productMetrics,
            'indexability_metrics' => $indexabilityMetrics,
            'content_quality_metrics' => $contentQualityMetrics,
            'overview' => $productMetrics,
            'sitemap' => [
                'exists' => view()->exists('sitemap-garages'),
                'url_count' => $sitemapUrls->count(),
                'eligible_count' => $eligibleUrls->count(),
                'duplicate_urls' => $sitemapUrlCounts->filter(fn (int $count): bool => $count > 1)->keys()->values()->all(),
                'duplicate_canonical_urls' => $duplicateCanonicals->keys()->values()->all(),
                'noindex_urls' => $sitemapInspections['noindex_urls'],
                'demo_outreach_urls' => $sitemapInspections['demo_outreach_urls'],
                'redirect_urls' => $sitemapInspections['redirect_urls'],
                'not_found_urls' => $sitemapInspections['not_found_urls'],
                'not_eligible_urls' => $sitemapNotEligible->all(),
                'eligible_missing_urls' => $eligibleMissingFromSitemap->all(),
                'urls' => $sitemapUrls->all(),
            ],
            'structured_data' => [
                'webpage_schema_pages' => count($garageInspections['webpage_schema_urls']),
                'vehicle_schema_pages' => count($garageInspections['vehicle_schema_urls']),
                'product_schema_pages' => count($garageInspections['product_schema_urls']),
                'product_schema_urls' => $garageInspections['product_schema_urls'],
                'error_urls' => $structuredDataErrorUrls->all(),
            ],
            'canonical' => [
                'mismatches' => count($garageInspections['canonical_mismatch_urls']),
                'mismatch_urls' => $garageInspections['canonical_mismatch_urls'],
                'duplicate_canonicals' => $duplicateCanonicals->count(),
                'duplicate_canonical_urls' => $duplicateCanonicals->keys()->values()->all(),
                'querystring_issues' => count($garageInspections['querystring_issue_urls']),
                'querystring_issue_urls' => $garageInspections['querystring_issue_urls'],
                'host_mismatches' => count($garageInspections['canonical_host_mismatch_urls']),
                'host_mismatch_urls' => $garageInspections['canonical_host_mismatch_urls'],
                'redirect_candidates' => count($garageInspections['redirect_candidate_urls']),
                'redirect_candidate_urls' => $garageInspections['redirect_candidate_urls'],
                'redirect_chains' => count($garageInspections['redirect_chain_urls']),
                'redirect_chain_urls' => $garageInspections['redirect_chain_urls'],
            ],
            'garage_pages' => [
                'title_missing' => $garageInspections['title_missing_urls'],
                'meta_description_missing' => $garageInspections['meta_description_missing_urls'],
                'h1_missing' => $garageInspections['h1_missing_urls'],
                'webpage_schema_missing' => $garageInspections['webpage_schema_missing_urls'],
                'vehicle_schema_missing' => $garageInspections['vehicle_schema_missing_urls'],
            ],
            'indexability' => [
                'public_slug_but_noindex' => $publicWithSlugButNoindex->map(fn (Vehicle $vehicle): string => (string) $vehicle->public_slug)->values()->all(),
                'should_index_not_in_sitemap' => $eligibleMissingFromSitemap->all(),
                'sitemap_but_not_should_index' => $sitemapNotEligible->all(),
            ],
            'action_items' => $actionItems,
            'validation_shortlist' => $this->validationShortlist(
                $garageInspections,
                $sitemapInspections,
                $sitemapNotEligible,
            ),
        ];
    }

    public function inspectGarageHtml(string $html): array
    {
        return [
            'has_webpage_schema' => $this->containsSchemaType($html, 'WebPage'),
            'has_vehicle_schema' => $this->containsSchemaType($html, 'Vehicle'),
            'has_product_schema' => $this->containsSchemaType($html, 'Product'),
            'canonical' => $this->extractCanonical($html),
            'og_url' => $this->extractOgUrl($html),
            'has_noindex' => Str::contains($html, 'name="robots" content="noindex'),
            'has_title' => (bool) preg_match('/<title>\s*[^<]+\s*<\/title>/i', $html),
            'has_meta_description' => (bool) preg_match('/<meta\s+name="description"\s+content="[^"]+"/i', $html),
            'has_h1' => (bool) preg_match('/<h1\b[^>]*>\s*.+?\s*<\/h1>/is', $html),
        ];
    }

    private function allVehicles(): EloquentCollection
    {
        return Vehicle::query()
            ->with([
                'user',
                'maintenanceLogs' => fn ($query) => $query->latest('maintenance_date')->latest('id'),
            ])
            ->orderBy('id')
            ->get();
    }

    private function publicVehicles(): EloquentCollection
    {
        return Vehicle::query()
            ->with([
                'user',
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->where('is_public', true)
            ->orderBy('public_slug')
            ->get();
    }

    private function eligibleUrls(Collection|EloquentCollection $vehicles): Collection
    {
        return collect($vehicles)
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))
            ->map(fn (Vehicle $vehicle): string => $this->publicGarageService->canonicalUrl($vehicle))
            ->values();
    }

    private function sitemapGarageUrls(Collection|EloquentCollection $vehicles): Collection
    {
        $xml = view('sitemap-garages', ['vehicles' => $vehicles])->render();

        preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $xml, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $url): string => trim($url))
            ->filter()
            ->values();
    }

    private function inspectGarageVehicles(EloquentCollection $vehicles): array
    {
        $result = [
            'webpage_schema_urls' => [],
            'vehicle_schema_urls' => [],
            'product_schema_urls' => [],
            'canonical_mismatch_urls' => [],
            'canonical_host_mismatch_urls' => [],
            'querystring_issue_urls' => [],
            'redirect_candidate_urls' => [],
            'redirect_chain_urls' => [],
            'title_missing_urls' => [],
            'meta_description_missing_urls' => [],
            'h1_missing_urls' => [],
            'webpage_schema_missing_urls' => [],
            'vehicle_schema_missing_urls' => [],
            'canonical' => [],
        ];

        foreach ($vehicles->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug)) as $vehicle) {
            $canonicalUrl = $this->publicGarageService->canonicalUrl($vehicle);
            $response = $this->dispatchGaragePath('/garage/'.$vehicle->public_slug);
            $html = $this->responseBody($response);
            $inspection = $this->inspectGarageHtml($html);
            $url = $canonicalUrl;

            if ($inspection['canonical'] !== null) {
                $result['canonical'][] = $inspection['canonical'];
            }

            if ($response->isRedirection()) {
                $result['redirect_candidate_urls'][] = $url;
                $target = (string) $response->headers->get('Location');
                if ($target !== '' && $this->dispatchAbsoluteUrl($target)->isRedirection()) {
                    $result['redirect_chain_urls'][] = $url;
                }
            }

            if ($response->isOk()) {
                if ($inspection['has_webpage_schema']) {
                    $result['webpage_schema_urls'][] = $url;
                } else {
                    $result['webpage_schema_missing_urls'][] = $url;
                }

                if ($inspection['has_vehicle_schema']) {
                    $result['vehicle_schema_urls'][] = $url;
                } else {
                    $result['vehicle_schema_missing_urls'][] = $url;
                }

                if ($inspection['has_product_schema']) {
                    $result['product_schema_urls'][] = $url;
                }

                if ($inspection['canonical'] !== $canonicalUrl || $inspection['og_url'] !== $canonicalUrl) {
                    $result['canonical_mismatch_urls'][] = $url;
                }

                if ($this->canonicalHostMismatch($inspection['canonical'])) {
                    $result['canonical_host_mismatch_urls'][] = $url;
                }

                if (! $inspection['has_title']) {
                    $result['title_missing_urls'][] = $url;
                }

                if (! $inspection['has_meta_description']) {
                    $result['meta_description_missing_urls'][] = $url;
                }

                if (! $inspection['has_h1']) {
                    $result['h1_missing_urls'][] = $url;
                }
            }

            $queryResponse = $this->dispatchGaragePath('/garage/'.$vehicle->public_slug.'?seo_audit=1');
            if (! $queryResponse->isRedirection() || $queryResponse->headers->get('Location') !== $canonicalUrl) {
                $result['querystring_issue_urls'][] = $url;
            }
        }

        return $result;
    }

    private function inspectSitemapUrls(Collection $sitemapUrls, EloquentCollection $vehicles): array
    {
        $vehiclesByUrl = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))
            ->keyBy(fn (Vehicle $vehicle): string => $this->publicGarageService->canonicalUrl($vehicle));

        $result = [
            'noindex_urls' => [],
            'demo_outreach_urls' => [],
            'redirect_urls' => [],
            'not_found_urls' => [],
        ];

        foreach ($sitemapUrls as $url) {
            $response = $this->dispatchAbsoluteUrl($url);
            $html = $this->responseBody($response);
            $vehicle = $vehiclesByUrl->get($url);

            if ($response->isRedirection()) {
                $result['redirect_urls'][] = $url;
            }

            if ($response->getStatusCode() === 404) {
                $result['not_found_urls'][] = $url;
            }

            if ($response->isOk() && $this->inspectGarageHtml($html)['has_noindex']) {
                $result['noindex_urls'][] = $url;
            }

            if ($vehicle instanceof Vehicle && $this->publicGarageService->isOutreachDemoVehicle($vehicle)) {
                $result['demo_outreach_urls'][] = $url;
            }
        }

        return $result;
    }

    private function qualityScores(Collection|EloquentCollection $vehicles): Collection
    {
        return collect($vehicles)
            ->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->getKey() => $this->qualityScore->score($vehicle)]);
    }

    private function actionItems(EloquentCollection $vehicles, Collection $eligibleUrls, Collection $sitemapUrls, array $garageInspections, Collection $qualityByVehicle): array
    {
        $sets = [
            'missing_from_sitemap' => $eligibleUrls->diff($sitemapUrls)->flip(),
            'canonical_mismatch' => collect($garageInspections['canonical_mismatch_urls'])->flip(),
            'host_mismatch' => collect($garageInspections['canonical_host_mismatch_urls'])->flip(),
            'structured_data_error' => collect([
                ...$garageInspections['webpage_schema_missing_urls'],
                ...$garageInspections['vehicle_schema_missing_urls'],
                ...$garageInspections['product_schema_urls'],
            ])->unique()->flip(),
        ];

        return $vehicles
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))
            ->reject(fn (Vehicle $vehicle): bool => $this->publicGarageService->isOutreachDemoVehicle($vehicle))
            ->filter(fn (Vehicle $vehicle): bool => (bool) $vehicle->is_public && $this->publicGarageService->shouldIndex($vehicle))
            ->map(function (Vehicle $vehicle) use ($sets, $qualityByVehicle): ?array {
                $publicUrl = $this->publicGarageService->canonicalUrl($vehicle);
                $quality = $qualityByVehicle->get($vehicle->getKey()) ?? $this->qualityScore->score($vehicle);
                $reasonCodes = [];

                foreach ($sets as $code => $set) {
                    if ($set->has($publicUrl)) {
                        $reasonCodes[] = $code;
                    }
                }

                foreach (['missing_photo', 'no_maintenance_logs', 'short_log_descriptions', 'missing_vehicle_identity'] as $code) {
                    if (in_array($code, $quality['reason_codes'], true)) {
                        $reasonCodes[] = $code;
                    }
                }

                if (($quality['score'] ?? 0) < 60) {
                    $reasonCodes[] = 'low_quality_score';
                }

                $reasonCodes = array_values(array_unique($reasonCodes));

                if ($reasonCodes === []) {
                    return null;
                }

                return [
                    'vehicle_id' => $vehicle->getKey(),
                    'public_url' => $publicUrl,
                    'admin_url' => VehicleResource::getUrl('edit', ['record' => $vehicle]),
                    'brand' => (string) $vehicle->brand,
                    'model' => (string) $vehicle->model,
                    'year' => $vehicle->year,
                    'vehicle_label' => trim($this->publicGarageService->publicVehicleName($vehicle)) ?: 'Voertuig '.$vehicle->getKey(),
                    'indexability_status' => 'indexable',
                    'sitemap_status' => $sets['missing_from_sitemap']->has($publicUrl) ? 'missing' : 'included',
                    'quality_score' => (int) ($quality['score'] ?? 0),
                    'severity' => $this->severityForReasons($reasonCodes),
                    'reason_codes' => $reasonCodes,
                    'details' => $this->readableReasons($reasonCodes),
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $a, array $b): int => $this->severityWeight($b['severity']) <=> $this->severityWeight($a['severity']),
                fn (array $a, array $b): int => $a['quality_score'] <=> $b['quality_score'],
            ])
            ->values()
            ->all();
    }

    private function opportunityCounts(array $actionItems): array
    {
        $items = collect($actionItems);

        return [
            'missing_photo' => $items->filter(fn (array $item): bool => in_array('missing_photo', $item['reason_codes'], true))->count(),
            'no_maintenance_logs' => $items->filter(fn (array $item): bool => in_array('no_maintenance_logs', $item['reason_codes'], true))->count(),
            'short_log_descriptions' => $items->filter(fn (array $item): bool => in_array('short_log_descriptions', $item['reason_codes'], true))->count(),
            'missing_vehicle_identity' => $items->filter(fn (array $item): bool => in_array('missing_vehicle_identity', $item['reason_codes'], true))->count(),
            'low_quality_score' => $items->filter(fn (array $item): bool => in_array('low_quality_score', $item['reason_codes'], true))->count(),
        ];
    }

    private function contentQualityMetrics(Collection $indexableVehicles, Collection $qualityByVehicle, array $actionItems): array
    {
        $items = collect($actionItems);
        $scores = $qualityByVehicle->pluck('score');

        return [
            'indexable_garages_without_photo' => $items->filter(fn (array $item): bool => in_array('missing_photo', $item['reason_codes'], true))->count(),
            'indexable_garages_without_maintenance_logs' => $items->filter(fn (array $item): bool => in_array('no_maintenance_logs', $item['reason_codes'], true))->count(),
            'indexable_garages_with_short_or_empty_log_descriptions' => $items->filter(fn (array $item): bool => in_array('short_log_descriptions', $item['reason_codes'], true))->count(),
            'indexable_garages_with_incomplete_vehicle_identity' => $items->filter(fn (array $item): bool => in_array('missing_vehicle_identity', $item['reason_codes'], true))->count(),
            'indexable_garages_meeting_quality_criteria' => $indexableVehicles->filter(fn (Vehicle $vehicle): bool => ($qualityByVehicle->get($vehicle->getKey())['score'] ?? 0) >= 80)->count(),
            'average_quality_score' => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : 0.0,
            'score_bands' => [
                '0_39' => $scores->filter(fn (int $score): bool => $score <= 39)->count(),
                '40_59' => $scores->filter(fn (int $score): bool => $score >= 40 && $score <= 59)->count(),
                '60_79' => $scores->filter(fn (int $score): bool => $score >= 60 && $score <= 79)->count(),
                '80_100' => $scores->filter(fn (int $score): bool => $score >= 80)->count(),
            ],
        ];
    }

    private function severityForReasons(array $reasonCodes): string
    {
        if (array_intersect($reasonCodes, ['missing_from_sitemap', 'unexpected_noindex', 'canonical_mismatch', 'host_mismatch', 'structured_data_error']) !== []) {
            return 'warning';
        }

        return 'opportunity';
    }

    private function readableReasons(array $reasonCodes): array
    {
        $labels = [
            'missing_photo' => 'geen voertuigfoto',
            'no_maintenance_logs' => 'geen publieke onderhoudslogs',
            'short_log_descriptions' => 'korte publieke logomschrijving',
            'missing_vehicle_identity' => 'ontbrekend merk of model',
            'missing_from_sitemap' => 'pagina ontbreekt in sitemap',
            'unexpected_noindex' => 'pagina hoort indexeerbaar te zijn',
            'canonical_mismatch' => 'canonical of og:url wijkt af',
            'host_mismatch' => 'canonical gebruikt verkeerde host',
            'structured_data_error' => 'ontbrekende of ongeldige structured data',
            'low_quality_score' => 'lage paginavolledigheid',
        ];

        return collect($reasonCodes)->map(fn (string $code): string => $labels[$code] ?? $code)->values()->all();
    }

    private function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'warning' => 2,
            'opportunity' => 1,
            default => 0,
        };
    }

    private function validationShortlist(array $garageInspections, array $sitemapInspections, Collection $sitemapNotEligible): array
    {
        return collect([
            ...Arr::wrap($garageInspections['product_schema_urls']),
            ...Arr::wrap($garageInspections['canonical_mismatch_urls']),
            ...Arr::wrap($sitemapInspections['noindex_urls']),
            ...$sitemapNotEligible->all(),
        ])->unique()->values()->all();
    }

    private function dispatchGaragePath(string $path): Response
    {
        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';

        $request = Request::create($path, 'GET', [], [], [], [
            'HTTP_HOST' => $host,
            'HTTPS' => $scheme === 'https' ? 'on' : 'off',
        ]);

        return app()->handle($request);
    }

    private function dispatchAbsoluteUrl(string $url): Response
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $this->dispatchGaragePath($path.($query ? '?'.$query : ''));
    }

    private function responseBody(Response $response): string
    {
        return method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    }

    private function containsSchemaType(string $html, string $type): bool
    {
        return Str::contains($html, '"@type": "'.$type.'"')
            || Str::contains($html, '"@type":"'.$type.'"')
            || Str::contains($html, "'@type': '".$type."'");
    }

    private function extractCanonical(string $html): ?string
    {
        if (! preg_match('/<link\s+rel="canonical"\s+href="([^"]+)"/i', $html, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function extractOgUrl(string $html): ?string
    {
        if (! preg_match('/<meta\s+property="og:url"\s+content="([^"]+)"/i', $html, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function canonicalHostMismatch(?string $canonical): bool
    {
        if ($canonical === null) {
            return true;
        }

        return parse_url($canonical, PHP_URL_SCHEME) !== 'https'
            || parse_url($canonical, PHP_URL_HOST) !== parse_url(PublicSeoUrl::garage('host-check'), PHP_URL_HOST)
            || Str::contains($canonical, 'www.');
    }
}
