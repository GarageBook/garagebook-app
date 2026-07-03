<?php

namespace App\Console\Commands;

use App\Services\Seo\SeoHealthService;
use Illuminate\Console\Command;

class SeoAuditPublicGaragesCommand extends Command
{
    protected $signature = 'garagebook:seo-audit-public-garages';

    protected $description = 'Read-only SEO audit for public garage pages and sitemap eligibility.';

    public function handle(SeoHealthService $seoHealthService): int
    {
        $report = $seoHealthService->report();

        $this->line('Public garage SEO audit');
        $this->line('total public vehicles: '.$report['overview']['public_vehicles']);
        $this->line('sitemap eligible: '.$report['sitemap']['eligible_count']);
        $this->line('noindex but in sitemap: '.count($report['sitemap']['noindex_urls']));
        $this->line('sitemap URL gives redirect: '.count($report['sitemap']['redirect_urls']));
        $this->line('sitemap URL gives 404: '.count($report['sitemap']['not_found_urls']));
        $this->line('canonical mismatch: '.$report['canonical']['mismatches']);
        $this->line('duplicate canonical: '.$report['canonical']['duplicate_canonicals']);
        $this->line('Product schema present on garage page: '.$report['structured_data']['product_schema_pages']);
        $this->line('demo/outreach URLs indexable: '.$this->demoOutreachIndexableCount($report));
        $this->line('critical errors: '.$report['critical_errors']);
        $this->line('warnings: '.$report['warnings']);

        if ($report['sitemap']['urls'] !== []) {
            $this->line('sample sitemap URL: '.$report['sitemap']['urls'][0]);
        }

        return $report['critical_errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function demoOutreachIndexableCount(array $report): int
    {
        return $report['overview']['demo_outreach_vehicles'] > 0
            ? count($report['sitemap']['demo_outreach_urls'])
            : 0;
    }
}
