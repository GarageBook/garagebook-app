<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CachePublicResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCache($request, $response)) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        $maxAge = str_contains($contentType, 'text/html') ? 600 : 3600;

        $response->setPublic();
        $response->setMaxAge($maxAge);
        $response->setSharedMaxAge($maxAge);
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}, s-maxage={$maxAge}, stale-while-revalidate=86400");

        return $response;
    }

    private function shouldCache(Request $request, Response $response): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($request->query->count() > 0 || $request->user() !== null) {
            return false;
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            return false;
        }

        if ($response->getStatusCode() !== 200 || $response->headers->has('Set-Cookie')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        return str_starts_with($contentType, 'text/html')
            || str_starts_with($contentType, 'application/xml')
            || str_starts_with($contentType, 'text/plain');
    }
}
