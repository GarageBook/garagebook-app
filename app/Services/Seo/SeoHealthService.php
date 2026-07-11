<?php

namespace App\Services\Seo;

use App\Models\Vehicle;
use App\Services\PublicGarageService;
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
    ) {}

    public function report(): array
    {
        $vehicles = $this->publicVehicles();
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
        $weakPages = $this->weakPages($vehicles, $eligibleUrls);
        $publicWithSlugButNoindex = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug) && ! $this->publicGarageService->shouldIndex($vehicle))
            ->values();
        $indexablePublicGaragePages = $vehicles->filter(fn (Vehicle $vehicle): bool => $this->publicGarageService->shouldIndex($vehicle))->count();
        $totalVehicles = Vehicle::query()->count();

        $critical = [
            'product_schema' => count($garageInspections['product_schema_urls']),
            'noindex_urls_in_sitemap' => count($sitemapInspections['noindex_urls']),
            'demo_outreach_urls_in_sitemap' => count($sitemapInspections['demo_outreach_urls']),
            'canonical_mismatches' => count($garageInspections['canonical_mismatch_urls']),
            'duplicate_canonicals' => $duplicateCanonicals->count(),
            'sitemap_not_eligible' => $sitemapNotEligible->count(),
            'sitemap_redirects' => count($sitemapInspections['redirect_urls']),
            'sitemap_404s' => count($sitemapInspections['not_found_urls']),
            'redirect_chains' => count($garageInspections['redirect_chain_urls']),
        ];

        $warnings = [
            'public_slug_but_noindex' => $publicWithSlugButNoindex->count(),
            'weak_content' => count($weakPages),
            'no_photo' => collect($weakPages)->filter(fn (array $row): bool => in_array('geen foto', $row['reasons'], true))->count(),
            'no_public_logs' => collect($weakPages)->filter(fn (array $row): bool => in_array('geen publieke onderhoudslogs', $row['reasons'], true))->count(),
            'eligible_missing_from_sitemap' => $eligibleMissingFromSitemap->count(),
        ];

        return [
            'status' => array_sum($critical) > 0 ? 'fail' : (array_sum($warnings) > 0 ? 'warning' : 'pass'),
            'critical_errors' => array_sum($critical),
            'warnings' => array_sum($warnings),
            'critical' => $critical,
            'warning_counts' => $warnings,
            'overview' => [
                'total_vehicles' => $totalVehicles,
                'public_vehicles' => $vehicles->count(),
                'hidden_vehicles' => Vehicle::query()->where('is_public', false)->count(),
                'indexable_public_garage_pages' => $indexablePublicGaragePages,
                'noindex_public_garage_pages' => $vehicles->filter(fn (Vehicle $vehicle): bool => ! $this->publicGarageService->shouldIndex($vehicle))->count(),
                'vehicles_without_public_slug' => Vehicle::query()->where(fn ($query) => $query->whereNull('public_slug')->orWhere('public_slug', ''))->count(),
                'vehicles_without_photo' => Vehicle::query()->whereNull('photo')->where(fn ($query) => $query->whereNull('photos')->orWhere('photos', '[]'))->count(),
                'vehicles_without_maintenance' => Vehicle::query()->doesntHave('maintenanceLogs')->count(),
                'demo_outreach_vehicles' => $vehicles->filter(fn (Vehicle $vehicle): bool => $this->publicGarageService->isOutreachDemoVehicle($vehicle))->count(),
                'vehicles_with_public_slug' => $vehicles->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))->count(),
                'indexable_percentage' => $totalVehicles > 0 ? round(($indexablePublicGaragePages / $totalVehicles) * 100, 1) : 0.0,
            ],
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
            'weak_pages' => $weakPages,
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
            'has_noindex' => Str::contains($html, 'name="robots" content="noindex'),
            'has_title' => (bool) preg_match('/<title>\s*[^<]+\s*<\/title>/i', $html),
            'has_meta_description' => (bool) preg_match('/<meta\s+name="description"\s+content="[^"]+"/i', $html),
            'has_h1' => (bool) preg_match('/<h1\b[^>]*>\s*.+?\s*<\/h1>/is', $html),
        ];
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

                if ($inspection['canonical'] !== $canonicalUrl) {
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

    private function weakPages(EloquentCollection $vehicles, Collection $eligibleUrls): array
    {
        return $vehicles
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))
            ->map(function (Vehicle $vehicle) use ($eligibleUrls): ?array {
                $timelineItems = $this->publicGarageService->publicTimelineItems($vehicle);
                $photos = $this->publicGarageService->publicVehiclePhotos($vehicle);
                $shouldIndex = $this->publicGarageService->shouldIndex($vehicle);
                $isDemo = $this->publicGarageService->isOutreachDemoVehicle($vehicle);
                $publicUrl = $this->publicGarageService->canonicalUrl($vehicle);
                $reasons = [];

                if ($timelineItems === []) {
                    $reasons[] = 'geen publieke onderhoudslogs';
                }

                if ($photos === []) {
                    $reasons[] = 'geen foto';
                }

                if ($timelineItems !== [] && ! collect($timelineItems)->contains(fn (array $item): bool => mb_strlen(trim((string) ($item['description'] ?? ''))) >= 20)) {
                    $reasons[] = 'korte/lege logomschrijving';
                }

                if (! $shouldIndex) {
                    $reasons[] = 'wel public_slug maar shouldIndex false';
                }

                if ($vehicle->is_public && ! $eligibleUrls->contains($publicUrl)) {
                    $reasons[] = 'wel public maar niet sitemap eligible';
                }

                if ($reasons === []) {
                    return null;
                }

                return [
                    'vehicle' => trim($this->publicGarageService->publicVehicleName($vehicle)) ?: 'Voertuig '.$vehicle->id,
                    'slug' => (string) $vehicle->public_slug,
                    'owner' => $vehicle->user?->email ?? 'Onbekend',
                    'reasons' => $reasons,
                    'reason' => implode(', ', $reasons),
                    'public_url' => $publicUrl,
                    'status' => $isDemo ? 'demo' : ($shouldIndex ? 'weak' : 'noindex'),
                ];
            })
            ->filter()
            ->take(25)
            ->values()
            ->all();
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

    private function canonicalHostMismatch(?string $canonical): bool
    {
        if ($canonical === null) {
            return true;
        }

        return parse_url($canonical, PHP_URL_SCHEME) !== 'https'
            || parse_url($canonical, PHP_URL_HOST) !== 'app.garagebook.nl'
            || Str::contains($canonical, 'www.');
    }
}
