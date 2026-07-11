<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\SeoHealthOverview;
use App\Http\Controllers\Controller;
use App\Services\Seo\SeoHealthService;

class SeoHealthOverviewExportController extends Controller
{
    public function __invoke(SeoHealthService $seoHealthService)
    {
        if (! auth()->check()) {
            return redirect('/admin/login');
        }

        abort_unless(SeoHealthOverview::canAccess(), 403);

        return response()->streamDownload(function () use ($seoHealthService): void {
            echo "\xEF\xBB\xBF";

            $handle = fopen('php://output', 'w');
            $report = $seoHealthService->report();

            fputcsv($handle, ['section', 'metric', 'value', 'details', 'url', 'status']);

            fputcsv($handle, ['status', 'SEO Health status', strtoupper((string) ($report['status'] ?? 'fail')), '', '', '']);
            fputcsv($handle, ['status', 'Critical errors', $report['critical_errors'] ?? 0, '', '', '']);
            fputcsv($handle, ['status', 'Warnings', $report['warnings'] ?? 0, '', '', '']);

            foreach (($report['overview'] ?? []) as $metric => $value) {
                fputcsv($handle, ['indexability_overview', $this->label($metric), $value, '', '', '']);
            }

            fputcsv($handle, ['sitemap_health', 'URLs in sitemap-garages.xml', $report['sitemap']['url_count'] ?? 0, '', '', '']);
            fputcsv($handle, ['sitemap_health', 'Sitemap eligible', $report['sitemap']['eligible_count'] ?? 0, '', '', '']);
            fputcsv($handle, ['sitemap_health', 'Duplicate canonical URLs', count($report['sitemap']['duplicate_canonical_urls'] ?? []), '', '', '']);
            fputcsv($handle, ['sitemap_health', 'Noindex URLs in sitemap', count($report['sitemap']['noindex_urls'] ?? []), '', '', '']);
            fputcsv($handle, ['sitemap_health', 'Demo/outreach URLs in sitemap', count($report['sitemap']['demo_outreach_urls'] ?? []), '', '', '']);
            fputcsv($handle, ['sitemap_health', 'Niet eligible in sitemap', count($report['sitemap']['not_eligible_urls'] ?? []), '', '', '']);

            fputcsv($handle, ['structured_data_health', 'WebPage schema', $report['structured_data']['webpage_schema_pages'] ?? 0, '', '', '']);
            fputcsv($handle, ['structured_data_health', 'Vehicle schema', $report['structured_data']['vehicle_schema_pages'] ?? 0, '', '', '']);
            fputcsv($handle, ['structured_data_health', 'Product schema', $report['structured_data']['product_schema_pages'] ?? 0, '', '', '']);

            fputcsv($handle, ['canonical_health', 'Canonical mismatches', $report['canonical']['mismatches'] ?? 0, '', '', '']);
            fputcsv($handle, ['canonical_health', 'Duplicate canonicals', $report['canonical']['duplicate_canonicals'] ?? 0, '', '', '']);
            fputcsv($handle, ['canonical_health', 'Querystring issues', $report['canonical']['querystring_issues'] ?? 0, '', '', '']);
            fputcsv($handle, ['canonical_health', 'Host mismatches', $report['canonical']['host_mismatches'] ?? 0, '', '', '']);
            fputcsv($handle, ['canonical_health', 'Redirect candidates', $report['canonical']['redirect_candidates'] ?? 0, '', '', '']);

            foreach (($report['weak_pages'] ?? []) as $row) {
                fputcsv($handle, [
                    'weak_pages',
                    $row['vehicle'] ?? '',
                    $row['slug'] ?? '',
                    ($row['owner'] ?? '').' | '.($row['reason'] ?? ''),
                    $row['public_url'] ?? '',
                    $row['status'] ?? '',
                ]);
            }

            foreach (($report['validation_shortlist'] ?? []) as $url) {
                fputcsv($handle, ['gsc_validation_shortlist', 'URL', '', '', $url, '']);
            }

            fclose($handle);
        }, 'seo-health-dashboard-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function label(string $metric): string
    {
        return str($metric)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }
}
