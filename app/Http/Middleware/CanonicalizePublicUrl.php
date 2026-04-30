<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalizePublicUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $this->shouldSkip($request)) {
            return $next($request);
        }

        $canonicalHost = $request->getHost() === 'www.garagebook.nl'
            ? 'garagebook.nl'
            : $request->getHost();

        $canonicalPath = $request->getPathInfo() !== '/'
            ? rtrim($request->getPathInfo(), '/')
            : '/';

        if ($canonicalHost !== $request->getHost() || $canonicalPath !== $request->getPathInfo()) {
            $queryString = $request->getQueryString();
            $targetUrl = 'https://' . $canonicalHost . $canonicalPath;

            if ($queryString) {
                $targetUrl .= '?' . $queryString;
            }

            return redirect()->to($targetUrl, 301);
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('admin') || $request->is('admin/*');
    }
}
