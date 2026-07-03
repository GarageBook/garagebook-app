<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\PublicGarageService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoAuditPublicGaragesCommand extends Command
{
    protected $signature = 'garagebook:seo-audit-public-garages';

    protected $description = 'Read-only SEO audit for public garage pages and sitemap eligibility.';

    public function handle(PublicGarageService $publicGarageService): int
    {
        $vehicles = Vehicle::query()
            ->with([
                'user',
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->where('is_public', true)
            ->orderBy('public_slug')
            ->get();

        $sitemapVehicles = $publicGarageService->indexableVehicles();
        $sitemapUrls = $sitemapVehicles
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))
            ->map(fn (Vehicle $vehicle): string => $publicGarageService->canonicalUrl($vehicle))
            ->values();

        $sitemapUrlRedirects = 0;
        $sitemapUrl404s = 0;
        $canonicalMismatches = 0;
        $productSchemaPages = 0;

        foreach ($vehicles->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug)) as $vehicle) {
            $response = $this->dispatchGaragePath('/garage/'.$vehicle->public_slug);
            $body = $this->responseBody($response);
            $canonicalUrl = $publicGarageService->canonicalUrl($vehicle);

            if ($response->isOk() && ! str_contains($body, '<link rel="canonical" href="'.$canonicalUrl.'">')) {
                $canonicalMismatches++;
            }

            if ($response->isOk() && $this->containsProductSchema($body)) {
                $productSchemaPages++;
            }
        }

        foreach ($sitemapVehicles->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug)) as $vehicle) {
            $response = $this->dispatchGaragePath('/garage/'.$vehicle->public_slug);

            if ($response->isRedirection()) {
                $sitemapUrlRedirects++;
            }

            if ($response->getStatusCode() === 404) {
                $sitemapUrl404s++;
            }
        }

        $canonicalCounts = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => filled($vehicle->public_slug))
            ->map(fn (Vehicle $vehicle): string => $publicGarageService->canonicalUrl($vehicle))
            ->countBy();

        $duplicateCanonicalCount = $canonicalCounts
            ->filter(fn (int $count): bool => $count > 1)
            ->count();

        $noindexButInSitemap = $sitemapVehicles
            ->filter(fn (Vehicle $vehicle): bool => ! $publicGarageService->shouldIndex($vehicle))
            ->count();

        $demoOutreachIndexable = $vehicles
            ->filter(fn (Vehicle $vehicle): bool => $publicGarageService->isOutreachDemoVehicle($vehicle) && $publicGarageService->shouldIndex($vehicle))
            ->count();

        $this->line('Public garage SEO audit');
        $this->line('total public vehicles: '.$vehicles->count());
        $this->line('sitemap eligible: '.$sitemapVehicles->count());
        $this->line('noindex but in sitemap: '.$noindexButInSitemap);
        $this->line('sitemap URL gives redirect: '.$sitemapUrlRedirects);
        $this->line('sitemap URL gives 404: '.$sitemapUrl404s);
        $this->line('canonical mismatch: '.$canonicalMismatches);
        $this->line('duplicate canonical: '.$duplicateCanonicalCount);
        $this->line('Product schema present on garage page: '.$productSchemaPages);
        $this->line('demo/outreach URLs indexable: '.$demoOutreachIndexable);

        if ($sitemapUrls->isNotEmpty()) {
            $this->line('sample sitemap URL: '.$sitemapUrls->first());
        }

        return self::SUCCESS;
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

    private function responseBody(Response $response): string
    {
        return method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    }

    private function containsProductSchema(string $body): bool
    {
        return str_contains($body, '"@type": "Product"')
            || str_contains($body, '"@type":"Product"')
            || str_contains($body, "'@type': 'Product'");
    }
}
