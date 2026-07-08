<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\SearchConsoleInsights;
use App\Http\Controllers\Controller;
use App\Services\Gsc\SearchConsoleInsightsService;

class SearchConsoleInsightsExportController extends Controller
{
    public function __invoke(SearchConsoleInsightsService $service)
    {
        if (! auth()->check()) {
            return redirect('/admin/login');
        }

        abort_unless(SearchConsoleInsights::canAccess(), 403);

        return response()->streamDownload(function () use ($service): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'section',
                'query',
                'page_url',
                'path',
                'page_type',
                'clicks',
                'impressions',
                'ctr',
                'position',
                'previous_position',
                'position_delta',
            ]);

            foreach ($service->exportRows() as $row) {
                fputcsv($handle, [
                    $row['section'] ?? '',
                    $row['query'] ?? '',
                    $row['page_url'] ?? '',
                    $row['path'] ?? '',
                    $row['page_type'] ?? '',
                    $row['clicks'] ?? '',
                    $row['impressions'] ?? '',
                    $row['ctr'] ?? '',
                    $row['position'] ?? '',
                    $row['previous_position'] ?? '',
                    $row['position_delta'] ?? '',
                ]);
            }
        }, 'search-console-insights-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
