<?php

namespace App\Support;

use Illuminate\Support\Str;

class PublicSeoUrl
{
    public const HOST = 'garagebook.nl';

    public const APP_HOST = 'app.garagebook.nl';

    public static function base(): string
    {
        return 'https://'.self::HOST;
    }

    public static function appBase(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($host !== self::APP_HOST) {
            $host = self::APP_HOST;
        }

        return 'https://'.$host;
    }

    public static function path(string $path): string
    {
        return self::base().'/'.ltrim($path, '/');
    }

    public static function root(): string
    {
        return self::base().'/';
    }

    public static function blogIndex(): string
    {
        return self::path('/blogs');
    }

    public static function blog(string $slug): string
    {
        return self::path('/blog/'.trim($slug, '/').'/');
    }

    public static function garage(string $publicSlug): string
    {
        return self::appBase().route('public-garage.show', ['publicSlug' => trim($publicSlug, '/')], false);
    }

    public static function page(string $slug): string
    {
        return self::path('/'.trim($slug, '/'));
    }

    public static function normalizeConfiguredCanonical(?string $canonicalUrl, string $fallbackPath): string
    {
        $canonicalUrl = trim((string) $canonicalUrl);

        if ($canonicalUrl === '') {
            return self::path($fallbackPath);
        }

        $scheme = parse_url($canonicalUrl, PHP_URL_SCHEME);
        $host = parse_url($canonicalUrl, PHP_URL_HOST);

        if ($scheme === 'https' && $host !== 'app.garagebook.nl') {
            return $canonicalUrl;
        }

        $path = parse_url($canonicalUrl, PHP_URL_PATH);
        $query = parse_url($canonicalUrl, PHP_URL_QUERY);
        $fragment = parse_url($canonicalUrl, PHP_URL_FRAGMENT);

        $normalized = self::path($path ?: $fallbackPath);

        if (filled($query)) {
            $normalized .= '?'.$query;
        }

        if (filled($fragment)) {
            $normalized .= '#'.$fragment;
        }

        return Str::replace('https://app.garagebook.nl', self::base(), $normalized);
    }
}
