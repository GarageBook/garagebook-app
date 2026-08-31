<?php

namespace App\Http\Middleware;

use App\Services\Outreach\OutreachDemoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMarktplaatsDemoAccountRoutes
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(OutreachDemoService::class)->marktplaats2026DemoContextForAuthenticatedUser();

        if (! is_array($context)) {
            return $next($request);
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        if ($this->isAllowedDemoRoute($request)) {
            return $next($request);
        }

        return redirect()->to('/admin');
    }

    private function isAllowedDemoRoute(Request $request): bool
    {
        return $request->is('admin')
            || $request->is('admin/tijdlijn')
            || $request->is('admin/vehicles/create');
    }
}
