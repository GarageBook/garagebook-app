<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Outreach\OutreachDemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartRedirectController extends Controller
{
    private const FORWARDED_PARAMETERS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'source',
        'campaign_slug',
        'partner_slug',
        'gclid',
        '_gl',
    ];

    public function __invoke(Request $request, OutreachDemoService $demoService): RedirectResponse
    {
        $allowedParameters = array_fill_keys(self::FORWARDED_PARAMETERS, true);
        $query = [];

        foreach ($request->query() as $parameter => $value) {
            if (! array_key_exists($parameter, $allowedParameters)) {
                continue;
            }

            if (is_string($value) && $value !== '') {
                $query[$parameter] = $value;
            }
        }

        if (filled($query['partner_slug'] ?? null) && filled($query['campaign_slug'] ?? null)) {
            $demoRoute = $demoService->demoRouteForGrowthPartner(
                $query['partner_slug'],
                $query['campaign_slug'],
            );

            return redirect()->to($demoRoute.'?'.http_build_query($query));
        }

        return redirect()->route('filament.admin.auth.register', $query);
    }
}
