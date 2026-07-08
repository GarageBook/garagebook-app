<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\SearchConsoleInsights;
use App\Http\Controllers\Controller;
use App\Services\Gsc\SeoOpportunityService;
use Illuminate\Http\Request;

class SeoOpportunitiesExportController extends Controller
{
    public function __invoke(Request $request, SeoOpportunityService $service)
    {
        if (! auth()->check()) {
            return redirect('/admin/login');
        }

        abort_unless(SearchConsoleInsights::canAccess(), 403);

        $filters = $request->only(['type', 'page_type', 'min_score', 'brand', 'date']);

        return response()->streamDownload(function () use ($service, $filters): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'date',
                'score',
                'priority',
                'effort',
                'type',
                'title',
                'description',
                'page_url',
                'path',
                'query',
                'page_type',
                'brand',
                'recommended_action',
                'impressions',
                'clicks',
                'ctr',
                'position',
                'previous_position',
                'position_delta',
            ]);

            foreach ($service->exportRows($filters) as $row) {
                $metadata = $row['metadata'] ?? [];

                fputcsv($handle, [
                    $row['date'] ?? '',
                    $row['impact_score'] ?? '',
                    $row['priority'] ?? '',
                    $row['effort'] ?? '',
                    $row['type'] ?? '',
                    $row['title'] ?? '',
                    $row['description'] ?? '',
                    $row['page_url'] ?? '',
                    $row['path'] ?? '',
                    $row['query'] ?? '',
                    $row['page_type'] ?? '',
                    $row['brand'] ?? '',
                    $row['recommended_action'] ?? '',
                    $metadata['impressions'] ?? '',
                    $metadata['clicks'] ?? '',
                    $metadata['ctr'] ?? '',
                    $metadata['position'] ?? '',
                    $metadata['previous_position'] ?? '',
                    $metadata['position_delta'] ?? '',
                ]);
            }
        }, 'seo-opportunities-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
