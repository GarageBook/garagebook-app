<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserAttribution;
use Illuminate\Http\Request;

class AnalyticsAttribution
{
    public const SESSION_KEY = 'garagebook.analytics_attribution';

    public function captureFromRequest(Request $request): void
    {
        if (! $request->hasSession() || $request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $payload = $this->buildPayloadFromRequest($request);

        if ($payload === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $payload);
    }

    public function pullForUser(User $user): ?UserAttribution
    {
        if (! session()->has(self::SESSION_KEY)) {
            return null;
        }

        $payload = session()->pull(self::SESSION_KEY);

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $user->attribution()->create($this->sanitizePayload($payload));
    }

    public function current(): ?array
    {
        $payload = session(self::SESSION_KEY);

        return is_array($payload) && $payload !== []
            ? $this->sanitizePayload($payload)
            : null;
    }

    private function buildPayloadFromRequest(Request $request): ?array
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }

        $payload = $this->sanitizePayload([
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_content' => $request->query('utm_content'),
            'utm_term' => $request->query('utm_term'),
            'landing_page' => $request->getPathInfo(),
            'referrer' => $this->externalReferrer($request),
        ]);

        $hasUtm = collect([
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term',
        ])->contains(fn (string $key): bool => filled($payload[$key] ?? null));

        if (! $hasUtm && blank($payload['referrer'] ?? null)) {
            return null;
        }

        return $payload;
    }

    private function externalReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! filled($referrer)) {
            return null;
        }

        $referrerHost = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($referrerHost) || blank($referrerHost)) {
            return null;
        }

        return $referrerHost === $request->getHost()
            ? null
            : $referrer;
    }

    private function sanitizePayload(array $payload): array
    {
        return array_filter(
            array_map(function (mixed $value): ?string {
                if (! is_string($value)) {
                    return null;
                }

                $value = trim($value);

                return $value !== '' ? mb_substr($value, 0, 2048) : null;
            }, $payload),
            fn (mixed $value): bool => $value !== null
        );
    }
}
