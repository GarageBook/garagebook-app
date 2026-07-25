<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoHealthService;

class SeoHealthDashboardExportController extends Controller
{
    public function __invoke(SeoHealthService $seoHealthService)
    {
        if (! auth()->check()) {
            return redirect('/admin/login');
        }

        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        return response()->streamDownload(function () use ($seoHealthService): void {
            echo chr(0xEF).chr(0xBB).chr(0xBF);

            $handle = fopen('php://output', 'w');
            $report = $seoHealthService->report();

            fputcsv($handle, ['section', 'metric', 'value', 'details', 'public_url', 'admin_url', 'vehicle_id', 'quality_score', 'severity', 'reason_codes']);

            fputcsv($handle, ['status', 'SEO Health status', strtoupper((string) ($report['status'] ?? 'fail')), '', '', '', '', '', '', '']);
            fputcsv($handle, ['status', 'Critical errors', $report['critical_errors'] ?? 0, '', '', '', '', '', 'critical', '']);
            fputcsv($handle, ['status', 'Warnings', $report['warnings'] ?? 0, '', '', '', '', '', 'warning', '']);
            fputcsv($handle, ['status', 'Opportunities', $report['opportunity_count'] ?? 0, '', '', '', '', '', 'opportunity', '']);

            foreach (($report['product_metrics'] ?? []) as $metric => $value) {
                fputcsv($handle, ['product_metrics', $this->label($metric), $this->formatValue($value), '', '', '', '', '', 'informational', '']);
            }

            foreach (($report['indexability_metrics'] ?? []) as $metric => $value) {
                fputcsv($handle, ['indexability_metrics', $this->label($metric), $this->formatValue($value), '', '', '', '', '', $this->metricSeverity($metric), '']);
            }

            foreach (($report['content_quality_metrics'] ?? []) as $metric => $value) {
                fputcsv($handle, ['content_quality_metrics', $this->label($metric), $this->formatValue($value), '', '', '', '', '', 'opportunity', '']);
            }

            foreach (($report['action_items'] ?? []) as $row) {
                fputcsv($handle, [
                    'action_items',
                    $row['vehicle_label'] ?? '',
                    '',
                    implode(' | ', $row['details'] ?? []),
                    $row['public_url'] ?? '',
                    $row['admin_url'] ?? '',
                    $row['vehicle_id'] ?? '',
                    $row['quality_score'] ?? '',
                    $row['severity'] ?? '',
                    implode('|', $row['reason_codes'] ?? []),
                ]);
            }

            foreach (($report['validation_shortlist'] ?? []) as $url) {
                fputcsv($handle, ['gsc_validation_shortlist', 'URL', '', '', $url, '', '', '', 'warning', '']);
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

    private function formatValue(mixed $value): string|int|float
    {
        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->map(fn (mixed $count, string $band): string => $band.': '.$count)
            ->implode(' | ');
    }

    private function metricSeverity(string $metric): string
    {
        return in_array($metric, ['noindex_in_sitemap', 'demo_outreach_indexable'], true) ? 'critical' : 'warning';
    }
}
