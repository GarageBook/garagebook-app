<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
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
        'gclid',
        '_gl',
    ];

    public function __invoke(Request $request): RedirectResponse
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

        return redirect()->route('filament.admin.auth.register', $query);
    }
}
