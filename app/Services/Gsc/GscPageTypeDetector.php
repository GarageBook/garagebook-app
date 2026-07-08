<?php

namespace App\Services\Gsc;

class GscPageTypeDetector
{
    public function pathFromUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = str_starts_with($url, '/') ? $url : '/';
        }

        $path = '/'.ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function detect(?string $urlOrPath): string
    {
        $path = $this->pathFromUrl($urlOrPath);

        if ($path === '/') {
            return 'homepage';
        }

        if ($path === null) {
            return 'other';
        }

        if (str_starts_with($path, '/garage/')) {
            return 'garage_page';
        }

        if (str_starts_with($path, '/onderhoud/')) {
            return 'vehicle_authority';
        }

        if ($this->isSeoPage($path)) {
            return 'seo_page';
        }

        if ($this->isStaticPage($path)) {
            return 'static_page';
        }

        return 'other';
    }

    private function isSeoPage(string $path): bool
    {
        return str_contains($path, 'onderhoudsboekje')
            || str_contains($path, 'onderhoud-bijhouden')
            || str_contains($path, 'onderhoud-app')
            || str_contains($path, 'digitaal-onderhoud');
    }

    private function isStaticPage(string $path): bool
    {
        return in_array($path, [
            '/contact',
            '/ons-verhaal',
            '/privacy-statement',
            '/algemene-voorwaarden',
            '/blogs',
        ], true);
    }
}
