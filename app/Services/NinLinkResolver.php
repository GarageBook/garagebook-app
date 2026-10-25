<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class NinLinkResolver
{
    public function resolve(Vehicle $vehicle): ?string
    {
        if (! config('nin-cross-promo.enabled')) {
            return null;
        }

        $brand = trim((string) $vehicle->brand);
        $model = trim((string) $vehicle->model);

        if ($brand === '' || $model === '') {
            return null;
        }

        // NIN currently rejects vehicle_type. GarageBook has no public plate consent.
        $key = 'nin_lookup:'.hash('sha256', mb_strtolower($brand).'|'.mb_strtolower($model));
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return ($cached['status'] ?? null) === 'match'
                ? $cached['url']
                : $this->lastMatch($key, $cached['status'] ?? null);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(config('nin-cross-promo.connect_timeout'))
                ->timeout(config('nin-cross-promo.timeout'))
                ->withoutRedirecting()
                ->post(config('nin-cross-promo.lookup_url'), [
                    'brand' => $brand,
                    'model' => $model,
                ]);

            $result = $response->successful() ? $response->json() : null;
        } catch (Throwable) {
            $result = null;
        }

        if (is_array($result) && in_array($result['status'] ?? null, ['registration', 'model'], true)) {
            $url = $result['url'] ?? null;

            if ($this->isNinUrl($url)) {
                Cache::put($key, ['status' => 'match', 'url' => $url], config('nin-cross-promo.match_ttl'));
                Cache::put($key.':last_match', $url, config('nin-cross-promo.last_match_ttl'));

                return $url;
            }
        }

        if (is_array($result) && ($result['status'] ?? null) === 'no_match') {
            Cache::forget($key.':last_match');
            Cache::put($key, ['status' => 'no_match'], config('nin-cross-promo.no_match_ttl'));

            return null;
        }

        Cache::put($key, ['status' => 'unavailable'], config('nin-cross-promo.unavailable_ttl'));

        return $this->lastMatch($key, 'unavailable');
    }

    private function lastMatch(string $key, ?string $status): ?string
    {
        if ($status !== 'unavailable') {
            return null;
        }

        $url = Cache::get($key.':last_match');

        return $this->isNinUrl($url) ? $url : null;
    }

    private function isNinUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'noginnederland.nl'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['port'])
            && filled($parts['path'] ?? null);
    }
}
