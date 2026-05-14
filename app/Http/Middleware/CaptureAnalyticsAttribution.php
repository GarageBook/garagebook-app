<?php

namespace App\Http\Middleware;

use App\Support\AnalyticsAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAnalyticsAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        app(AnalyticsAttribution::class)->captureFromRequest($request);

        return $next($request);
    }
}
