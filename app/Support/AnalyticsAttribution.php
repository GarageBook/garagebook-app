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
        if (! $request->hasSession()) {
            return;
        }

        $existing = $request->session()->get(self::SESSION_KEY);

        if (is_array($existing) && $existing !== []) {
            $payload = $this->buildPayloadFromRequest($request);

            if ($payload !== null) {
                $request->session()->put(self::SESSION_KEY, $this->mergeRegistrationContext($existing, $payload));
            }

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
            'source' => $request->query('source'),
            'campaign_slug' => $request->query('campaign_slug'),
            'partner_slug' => $request->query('partner_slug'),
            'prospect_id' => $request->query('prospect_id'),
            'demo_user_id' => $request->query('demo_user_id'),
            'outreach_prospect_id' => $request->query('outreach_prospect_id'),
            'intended' => $request->query('intended'),
            'utm_source' => $this->firstTouchValue($request, 'attr_source') ?? $request->query('utm_source'),
            'utm_medium' => $this->firstTouchValue($request, 'attr_medium') ?? $request->query('utm_medium'),
            'utm_campaign' => $this->firstTouchValue($request, 'attr_campaign') ?? $request->query('utm_campaign'),
            'utm_content' => $request->query('utm_content'),
            'utm_term' => $request->query('utm_term'),
            'gclid' => $request->query('gclid'),
            'landing_page' => $this->landingPage($request),
            'referrer' => $this->firstTouchReferrer($request) ?? $this->externalReferrer($request),
        ]);

        $hasUtm = collect([
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term',
            'gclid',
            'source',
            'campaign_slug',
            'partner_slug',
            'prospect_id',
            'demo_user_id',
            'outreach_prospect_id',
            'intended',
        ])->contains(fn (string $key): bool => filled($payload[$key] ?? null));

        if (! $hasUtm && blank($payload['referrer'] ?? null) && $this->firstTouchLanding($request) === null) {
            return null;
        }

        return $payload;
    }

    /**
     * Preserve first-touch attribution while allowing the demo CTA to add
     * registration-specific context for the same Marktplaats prospect.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeRegistrationContext(array $existing, array $payload): array
    {
        $existing = $this->sanitizePayload($existing);
        $payload = $this->sanitizePayload($payload);

        if (($existing['campaign_slug'] ?? null) !== 'marktplaats2026'
            || ($payload['campaign_slug'] ?? null) !== 'marktplaats2026'
            || ($existing['prospect_id'] ?? null) !== ($payload['prospect_id'] ?? null)) {
            return $existing;
        }

        foreach (['demo_user_id', 'outreach_prospect_id', 'intended'] as $key) {
            if (filled($payload[$key] ?? null)) {
                $existing[$key] = $payload[$key];
            }
        }

        return $existing;
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

    private function landingPage(Request $request): string
    {
        return $this->firstTouchLanding($request) ?? $request->getPathInfo();
    }

    private function firstTouchLanding(Request $request): ?string
    {
        if (! $this->acceptsPublicFirstTouch($request)) {
            return null;
        }

        $path = $request->query('attr_landing');

        if (! is_string($path)
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || strpbrk($path, "\\?#\r\n") !== false
            || preg_match('/[[:cntrl:]]|(?:^|\/)\.{1,2}(?:\/|$)/u', $path)
            || mb_strlen($path) > 255) {
            return null;
        }

        return $path;
    }

    private function firstTouchValue(Request $request, string $key): ?string
    {
        if (! $this->acceptsPublicFirstTouch($request)) {
            return null;
        }

        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' && mb_strlen($value) <= 255 && ! preg_match('/[[:cntrl:]]/u', $value)
            ? $value
            : null;
    }

    private function firstTouchReferrer(Request $request): ?string
    {
        if (! $this->acceptsPublicFirstTouch($request)) {
            return null;
        }

        $referrer = $request->query('attr_referrer');

        if (! is_string($referrer) || strlen($referrer) > 2048) {
            return null;
        }

        $parts = parse_url($referrer);

        if (! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! preg_match('/^[a-z0-9.-]+$/i', $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function acceptsPublicFirstTouch(Request $request): bool
    {
        return in_array($request->getPathInfo(), [
            '/start',
            '/register',
            '/admin/register',
            '/admin/register/geratel',
        ], true);
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
