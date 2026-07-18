<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Support\PublicSeoUrl;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SeoAuditCommand extends Command
{
    protected $signature = 'garagebook:seo-audit {--format=text : text or json}';

    protected $description = 'Run the SEO quality gate for sitemaps, canonicals, structured data, redirects and indexability.';

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, int> */
    private array $counts = [
        'sitemap' => 0,
        'garage_pages' => 0,
        'redirects' => 0,
        'indexability' => 0,
    ];

    public function handle(PublicGarageService $publicGarageService): int
    {
        $this->errors = [];
        $this->counts = array_fill_keys(array_keys($this->counts), 0);

        $sitemapUrls = $this->auditSitemaps($publicGarageService);
        $this->auditGaragePages($publicGarageService, $sitemapUrls['garage']);
        $this->auditIndexability($publicGarageService, $sitemapUrls['garage']);

        $failed = collect($this->counts)->sum() > 0;

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'status' => $failed ? 'FAIL' : 'PASS',
                'errors' => $this->errors,
                'counts' => $this->counts,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTextReport($failed);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{all:Collection<int, string>, garage:Collection<int, string>}
     */
    private function auditSitemaps(PublicGarageService $publicGarageService): array
    {
        $allUrls = collect();
        $garageUrls = collect();

        $this->lineSection('=== SITEMAP ===');

        foreach (['/sitemap.xml', '/sitemap-garages.xml'] as $sitemapPath) {
            $response = $this->dispatchPath($sitemapPath);

            if (! $response->isOk()) {
                $this->recordFailure('sitemap', "sitemap bestaat: {$sitemapPath} returned {$response->getStatusCode()}");

                continue;
            }

            $this->pass("sitemap bestaat: {$sitemapPath}");
            $urls = $this->extractSitemapUrls($this->responseBody($response));

            if ($sitemapPath === '/sitemap-garages.xml') {
                $garageUrls = $urls;
            }

            $allUrls = $allUrls->merge($urls);
        }

        $allUrls->duplicates()->unique()->each(fn (string $url) => $this->recordFailure('sitemap', 'geen duplicates: '.$url));

        if ($allUrls->duplicates()->isEmpty()) {
            $this->pass('geen duplicates');
        }

        foreach ($allUrls as $url) {
            if (parse_url($url, PHP_URL_QUERY)) {
                $this->recordFailure('sitemap', 'geen querystrings: '.$url);
            }

            $chain = $this->redirectChain($url);
            $final = $chain[array_key_last($chain)]['response'];

            if (count($chain) > 1) {
                $this->recordFailure('sitemap', 'geen redirects: '.$url);
            }

            if (! $final->isOk()) {
                $this->recordFailure('sitemap', 'alle URLs geven 200: '.$url.' returned '.$final->getStatusCode());
            }

            if ($this->hasNoindex($this->responseBody($final))) {
                $this->recordFailure('sitemap', "geen noindex pagina's: {$url}");
            }

            if ($this->isDemoOrOutreachUrl($publicGarageService, $url)) {
                $this->recordFailure('sitemap', 'geen demo/outreach URLs: '.$url);
            }
        }

        $this->passWhenNoCategoryErrors('sitemap', 'alle URLs geven 200');
        $this->passWhenNoCategoryErrors('sitemap', 'geen redirects');
        $this->passWhenNoCategoryErrors('sitemap', 'geen querystrings');
        $this->passWhenNoCategoryErrors('sitemap', "geen noindex pagina's");
        $this->passWhenNoCategoryErrors('sitemap', 'geen demo/outreach URLs');

        return [
            'all' => $allUrls->values(),
            'garage' => $garageUrls->values(),
        ];
    }

    /** @param Collection<int, string> $garageSitemapUrls */
    private function auditGaragePages(PublicGarageService $publicGarageService, Collection $garageSitemapUrls): void
    {
        $this->lineSection('=== GARAGE PAGES ===');

        $garageSitemapSet = $garageSitemapUrls->flip();
        $vehicles = $this->publicVehicles($publicGarageService);

        foreach ($vehicles as $vehicle) {
            $canonicalUrl = $publicGarageService->canonicalUrl($vehicle);
            $response = $this->dispatchUrl($canonicalUrl);
            $body = $this->responseBody($response);
            $shouldIndex = $publicGarageService->shouldIndex($vehicle);

            if (! $response->isOk()) {
                $this->recordFailure('garage_pages', 'garage page 200: '.$canonicalUrl.' returned '.$response->getStatusCode());

                continue;
            }

            $canonical = $this->canonicalHref($body);

            if ($canonical !== $canonicalUrl) {
                $this->recordFailure('garage_pages', 'self canonical: '.$canonicalUrl.' has '.($canonical ?: '[missing]'));
            }

            if ($shouldIndex && ! $garageSitemapSet->has($canonicalUrl)) {
                $this->recordFailure('garage_pages', 'canonical = sitemap URL: '.$canonicalUrl.' missing from sitemap');
            }

            if (! $this->containsSchemaType($body, 'WebPage')) {
                $this->recordFailure('garage_pages', 'WebPage schema aanwezig: '.$canonicalUrl);
            }

            if (! $this->containsSchemaType($body, 'Vehicle')) {
                $this->recordFailure('garage_pages', 'Vehicle schema aanwezig: '.$canonicalUrl);
            }

            if ($this->containsSchemaType($body, 'Product')) {
                $this->recordFailure('garage_pages', 'GEEN Product schema: '.$canonicalUrl);
            }

            if (! $this->hasNonEmptyTag($body, 'title')) {
                $this->recordFailure('garage_pages', 'title aanwezig: '.$canonicalUrl);
            }

            if (! $this->hasMetaDescription($body)) {
                $this->recordFailure('garage_pages', 'meta description aanwezig: '.$canonicalUrl);
            }

            if (! $this->hasNonEmptyTag($body, 'h1')) {
                $this->recordFailure('garage_pages', 'H1 aanwezig: '.$canonicalUrl);
            }

            $queryChain = $this->redirectChain($canonicalUrl.'?seo_audit=1');
            $redirectCount = count($queryChain) - 1;

            if ($redirectCount > 1) {
                $this->recordFailure('redirects', 'exact 1 redirect max: '.$canonicalUrl.'?seo_audit=1');
                $this->recordFailure('redirects', 'geen redirect chains: '.$canonicalUrl.'?seo_audit=1');
            }

            if ($redirectCount === 1) {
                $location = $queryChain[0]['response']->headers->get('Location');

                if ($location !== $canonicalUrl) {
                    $this->recordFailure('redirects', 'exact 1 redirect max: '.$canonicalUrl.' redirects to '.($location ?: '[missing]'));
                }
            }

            if (str_starts_with((string) $canonical, 'http://')) {
                $this->recordFailure('redirects', 'geen http canonical: '.$canonicalUrl);
            }

            if (parse_url((string) $canonical, PHP_URL_HOST) === 'www.garagebook.nl') {
                $this->recordFailure('redirects', 'geen www canonical: '.$canonicalUrl);
            }
        }

        $this->passWhenNoCategoryErrors('garage_pages', 'self canonical');
        $this->passWhenNoCategoryErrors('garage_pages', 'canonical = sitemap URL');
        $this->passWhenNoCategoryErrors('garage_pages', 'WebPage schema aanwezig');
        $this->passWhenNoCategoryErrors('garage_pages', 'Vehicle schema aanwezig');
        $this->passWhenNoCategoryErrors('garage_pages', 'GEEN Product schema');
        $this->passWhenNoCategoryErrors('garage_pages', 'title aanwezig');
        $this->passWhenNoCategoryErrors('garage_pages', 'meta description aanwezig');
        $this->passWhenNoCategoryErrors('garage_pages', 'H1 aanwezig');

        $this->lineSection('=== REDIRECTS ===');
        $this->passWhenNoCategoryErrors('redirects', 'exact 1 redirect max');
        $this->passWhenNoCategoryErrors('redirects', 'geen redirect chains');
        $this->passWhenNoCategoryErrors('redirects', 'geen http canonical');
        $this->passWhenNoCategoryErrors('redirects', 'geen www canonical');
    }

    /** @param Collection<int, string> $garageSitemapUrls */
    private function auditIndexability(PublicGarageService $publicGarageService, Collection $garageSitemapUrls): void
    {
        $this->lineSection('=== INDEXABILITY ===');

        $garageSitemapSet = $garageSitemapUrls->flip();

        foreach ($this->publicVehicles($publicGarageService) as $vehicle) {
            $canonicalUrl = $publicGarageService->canonicalUrl($vehicle);
            $shouldIndex = $publicGarageService->shouldIndex($vehicle);
            $response = $this->dispatchUrl($canonicalUrl);
            $body = $this->responseBody($response);
            $inSitemap = $garageSitemapSet->has($canonicalUrl);
            $robotsNoindex = $this->hasNoindex($body);
            $canonical = $this->canonicalHref($body);

            if ($shouldIndex !== $inSitemap) {
                $this->recordFailure('indexability', 'shouldIndex consistent met sitemap: '.$canonicalUrl);
            }

            if ($shouldIndex === $robotsNoindex) {
                $this->recordFailure('indexability', 'shouldIndex consistent met robots: '.$canonicalUrl);
            }

            if ($shouldIndex && $canonical !== $canonicalUrl) {
                $this->recordFailure('indexability', 'shouldIndex consistent met canonical: '.$canonicalUrl);
            }
        }

        $this->passWhenNoCategoryErrors('indexability', 'shouldIndex consistent met sitemap');
        $this->passWhenNoCategoryErrors('indexability', 'shouldIndex consistent met robots');
        $this->passWhenNoCategoryErrors('indexability', 'shouldIndex consistent met canonical');
    }

    /** @return Collection<int, Vehicle> */
    private function publicVehicles(PublicGarageService $publicGarageService): Collection
    {
        return Vehicle::query()
            ->with([
                'user',
                'maintenanceLogs' => fn ($query) => $query
                    ->latest('maintenance_date')
                    ->latest('id'),
            ])
            ->where('is_public', true)
            ->whereNotNull('public_slug')
            ->orderBy('public_slug')
            ->get()
            ->values();
    }

    /** @return Collection<int, string> */
    private function extractSitemapUrls(string $xml): Collection
    {
        $matches = [];
        preg_match_all('/<loc>(.*?)<\/loc>/s', $xml, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $url): string => html_entity_decode(trim($url), ENT_QUOTES | ENT_XML1))
            ->filter()
            ->values();
    }

    /**
     * @return list<array{url:string, response:Response}>
     */
    private function redirectChain(string $url): array
    {
        $chain = [];
        $currentUrl = $url;

        for ($i = 0; $i < 5; $i++) {
            $response = $this->dispatchUrl($currentUrl);
            $chain[] = ['url' => $currentUrl, 'response' => $response];

            if (! $response->isRedirection()) {
                break;
            }

            $location = $response->headers->get('Location');

            if (! is_string($location) || trim($location) === '') {
                break;
            }

            $currentUrl = $this->absoluteUrl($location, $currentUrl);
        }

        return $chain;
    }

    private function dispatchUrl(string $url): Response
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $this->dispatchPath($path.($query ? '?'.$query : ''), $url);
    }

    private function dispatchPath(string $path, ?string $sourceUrl = null): Response
    {
        $appUrl = (string) config('app.url');
        $url = $sourceUrl ?: PublicSeoUrl::path($path);
        $host = parse_url($url, PHP_URL_HOST) ?: parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $requestPath = parse_url($url, PHP_URL_PATH) ?: $path;
        $query = parse_url($url, PHP_URL_QUERY) ?: '';

        $request = Request::create($requestPath.($query !== '' ? '?'.$query : ''), 'GET', [], [], [], [
            'HTTP_HOST' => $host,
            'HTTPS' => $scheme === 'https' ? 'on' : 'off',
        ]);

        return app()->handle($request);
    }

    private function absoluteUrl(string $location, string $baseUrl): string
    {
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: PublicSeoUrl::HOST;

        return $scheme.'://'.$host.'/'.ltrim($location, '/');
    }

    private function isDemoOrOutreachUrl(PublicGarageService $publicGarageService, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (! Str::startsWith($path, '/garage/')) {
            return false;
        }

        $slug = trim(Str::after($path, '/garage/'), '/');

        if ($slug === '' || str_contains($slug, '/')) {
            return true;
        }

        $vehicle = Vehicle::query()->with('user')->where('public_slug', $slug)->first();

        return $vehicle instanceof Vehicle && $publicGarageService->isOutreachDemoVehicle($vehicle);
    }

    private function responseBody(Response $response): string
    {
        return method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    }

    private function canonicalHref(string $body): ?string
    {
        if (preg_match('/<link\s+[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $body, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES);
        }

        if (preg_match('/<link\s+[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']canonical["\'][^>]*>/i', $body, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES);
        }

        return null;
    }

    private function hasNoindex(string $body): bool
    {
        return preg_match('/<meta\s+[^>]*name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $body) === 1;
    }

    private function containsSchemaType(string $body, string $type): bool
    {
        return str_contains($body, '"@type": "'.$type.'"')
            || str_contains($body, '"@type":"'.$type.'"')
            || str_contains($body, '"@type": ["'.$type.'"')
            || str_contains($body, "'@type': '".$type."'");
    }

    private function hasNonEmptyTag(string $body, string $tag): bool
    {
        return preg_match('/<'.$tag.'\b[^>]*>\s*[^<\s][\s\S]*?<\/'.$tag.'>/i', $body) === 1;
    }

    private function hasMetaDescription(string $body): bool
    {
        return preg_match('/<meta\s+[^>]*name=["\']description["\'][^>]*content=["\'][^"\']+/', $body) === 1;
    }

    private function recordFailure(string $category, string $message): void
    {
        $this->errors[$category][] = $message;
        $this->counts[$category]++;
        $this->line('✗ '.$message);
    }

    private function pass(string $message): void
    {
        $this->line('✓ '.$message);
    }

    private function passWhenNoCategoryErrors(string $category, string $message): void
    {
        if (($this->counts[$category] ?? 0) === 0) {
            $this->pass($message);
        }
    }

    private function lineSection(string $label): void
    {
        $this->newLine();
        $this->line($label);
    }

    private function renderTextReport(bool $failed): void
    {
        $this->newLine();
        $this->line('=== REPORT ===');
        foreach ($this->counts as $category => $count) {
            $this->line($category.': '.$count);
        }

        $this->newLine();
        $this->line($failed ? 'FAIL' : 'PASS');
    }
}
