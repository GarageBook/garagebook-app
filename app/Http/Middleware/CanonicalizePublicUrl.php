<?php

namespace App\Http\Middleware;

use App\Support\PublicSeoUrl;
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

        if ($indexHtmlRedirect = $this->indexHtmlRedirect($request)) {
            return $indexHtmlRedirect;
        }

        if ($blogRedirect = $this->blogRedirect($request)) {
            return $blogRedirect;
        }

        if ($request->getHost() === 'app.garagebook.nl' && ! $this->shouldSkipAppHostRedirect($request)) {
            return redirect()->to($this->targetUrl(PublicSeoUrl::HOST, $request->getPathInfo(), $request), 301);
        }

        $canonicalHost = $request->getHost() === 'www.garagebook.nl'
            ? PublicSeoUrl::HOST
            : $request->getHost();

        $canonicalPath = $request->getPathInfo() !== '/'
            ? rtrim($request->getPathInfo(), '/')
            : '/';

        if ($this->isCanonicalBlogDetailPath($request->getPathInfo()) || $request->getPathInfo() === '/youngtimer-onderhoud-bijhouden/') {
            $canonicalPath = $request->getPathInfo();
        }

        if ($canonicalHost !== $request->getHost() || $canonicalPath !== $request->getPathInfo()) {
            $targetUrl = $this->targetUrl($canonicalHost, $canonicalPath, $request);

            if ($targetUrl === $request->fullUrl()) {
                return $next($request);
            }

            return redirect()->to($targetUrl, 301);
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('admin') || $request->is('admin/*');
    }

    private function shouldSkipAppHostRedirect(Request $request): bool
    {
        return $request->is([
            'admin',
            'admin/*',
            'api',
            'api/*',
            'demo/garage/*',
            'documents/*',
            'email/*',
            'filament/*',
            'lifecycle-emails/*',
            'livewire/*',
            'livewire-*',
            'login',
            'logout',
            'maintenance/pdf',
            'password/*',
            'register',
            'share/*',
            'storage/*',
            'trips/*',
        ]);
    }

    private function indexHtmlRedirect(Request $request): ?Response
    {
        $path = $request->getPathInfo();

        if (! str_ends_with($path, '/index.html')) {
            return null;
        }

        if ($this->isProtectedIndexHtmlPath($request)) {
            return null;
        }

        $targetPath = substr($path, 0, -strlen('index.html'));

        return redirect()->to($this->targetUrl(PublicSeoUrl::HOST, $targetPath, $request), 301);
    }

    private function isProtectedIndexHtmlPath(Request $request): bool
    {
        return $request->is([
            'admin/*',
            'api/*',
            'blog-image/*',
            'build/*',
            'css/*',
            'filament/*',
            'images/*',
            'js/*',
            'livewire/*',
            'livewire-*',
            'storage/*',
            'vendor/*',
        ]);
    }

    private function blogRedirect(Request $request): ?Response
    {
        $path = $request->getPathInfo();

        if (preg_match('#^/blogs/([^/]+)$#', $path, $matches) === 1) {
            return redirect()->to($this->targetUrl(PublicSeoUrl::HOST, '/blog/'.$matches[1].'/', $request), 301);
        }
        if (preg_match('#^/blog/([^/]+)$#', $path, $matches) === 1) {
            $targetUrl = $this->targetUrl(PublicSeoUrl::HOST, '/blog/'.$matches[1].'/', $request);

            return $targetUrl === $request->fullUrl() ? null : redirect()->to($targetUrl, 301);
        }

        return null;
    }

    private function isCanonicalBlogDetailPath(string $path): bool
    {
        return preg_match('#^/blog/[^/]+/$#', $path) === 1;
    }

    private function targetUrl(string $host, string $path, Request $request): string
    {
        $targetUrl = 'https://'.$host.($path === '' ? '/' : $path);
        $queryString = $request->getQueryString();

        if ($queryString) {
            $targetUrl .= '?'.$queryString;
        }

        return $targetUrl;
    }
}
