<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Outreach\OutreachDemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartRedirectController extends Controller
{
    public function __invoke(Request $request, OutreachDemoService $demoService): RedirectResponse
    {
        $query = $request->query();
        $queryString = $request->server->get('QUERY_STRING', $request->getQueryString());

        if (filled($query['partner_slug'] ?? null) && filled($query['campaign_slug'] ?? null)) {
            $demoRoute = $demoService->demoRouteForGrowthPartner(
                $query['partner_slug'],
                $query['campaign_slug'],
            );

            return redirect()->to($this->withRawQueryString($demoRoute, $queryString));
        }

        return redirect()->to($this->withRawQueryString('https://app.garagebook.nl/admin/register', $queryString));
    }

    private function withRawQueryString(string $url, ?string $queryString): string
    {
        if ($queryString === null || $queryString === '') {
            return $url;
        }

        return $url.'?'.$queryString;
    }
}
