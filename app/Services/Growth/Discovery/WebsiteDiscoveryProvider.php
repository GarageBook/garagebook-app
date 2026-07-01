<?php

namespace App\Services\Growth\Discovery;

use App\Contracts\Growth\DiscoveryProvider;
use App\Data\Growth\DiscoveryResult;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebsiteDiscoveryProvider implements DiscoveryProvider
{
    /**
     * @param  array<int, string>  $urls
     */
    public function __construct(
        private readonly array $urls,
        private readonly int $limit = 100,
        private readonly ?int $fetchLimit = null,
    ) {}

    public function discover(): array
    {
        $results = [];

        foreach (array_slice(array_values(array_unique(array_filter($this->urls))), 0, $this->limit) as $index => $url) {
            $result = $this->discoverUrl($url, $this->fetchLimit === null || $index < $this->fetchLimit);

            if ($result instanceof DiscoveryResult) {
                $results[] = $result;
            }
        }

        return $results;
    }

    private function discoverUrl(string $url, bool $fetch = true): ?DiscoveryResult
    {
        $url = $this->normalizeUrl($url);

        if ($url === null) {
            return null;
        }

        if (! $fetch) {
            return $this->fallbackResult($url, 'Community2026 seed URL; website fetch skipped by batch limit.');
        }

        $main = $this->fetch($url);

        if ($main === null) {
            return $this->fallbackResult($url, 'Community2026 seed URL; website fetch failed or timed out.');
        }

        $contactUrl = $main['contact_url'] ?? null;
        $contact = is_string($contactUrl) ? $this->fetch($contactUrl) : null;

        $combined = $this->mergeData($main, $contact);
        $combined['source_type'] = 'website';
        $combined['source_url'] = $contact['source_url'] ?? $main['source_url'];

        if (! empty($combined['notes_fragments'])) {
            $combined['notes'] = trim(implode(' | ', array_unique(array_filter($combined['notes_fragments']))));
        }

        return DiscoveryResult::fromArray($combined, 'website');
    }

    private function fallbackResult(string $url, string $notes): DiscoveryResult
    {
        return DiscoveryResult::fromArray([
            'website' => $url,
            'source_url' => $url,
            'source_type' => 'website',
            'prospect_type' => 'community',
            'notes' => $notes,
        ], 'website');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $response = Http::connectTimeout(1)->timeout(2)->withHeaders(['Accept' => 'text/html'])->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = (string) $response->body();

        if (trim($body) === '') {
            return null;
        }

        return $this->extract($body, $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function extract(string $html, string $url): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $text = trim(preg_replace('/\s+/', ' ', $document->textContent ?? '') ?? '');
        $title = $this->meta($xpath, 'property', 'og:site_name')
            ?? $this->meta($xpath, 'name', 'application-name')
            ?? $this->meta($xpath, 'name', 'title')
            ?? $this->firstNodeText($xpath, '//h1')
            ?? $this->title($xpath)
            ?? null;
        $name = $title ?: null;
        $email = $this->mailto($xpath) ?? $this->regexMatch('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $html);
        $phone = $this->tel($xpath) ?? $this->regexMatch('/(?:\+?31|0)\s?[1-9][0-9\s().-]{7,}/', $text);
        $city = $this->meta($xpath, 'itemprop', 'addressLocality') ?? $this->meta($xpath, 'name', 'city');
        $province = $this->meta($xpath, 'itemprop', 'addressRegion') ?? $this->meta($xpath, 'name', 'province') ?? $this->provinceFromText($text);
        $contactUrl = $this->contactUrl($xpath, $url);
        $socialLinks = $this->socialLinks($xpath, $url);
        $notesFragments = [];

        if ($contactUrl !== null) {
            $notesFragments[] = 'contact_page: '.$contactUrl;
        }

        if ($socialLinks !== []) {
            $notesFragments[] = 'social: '.implode(', ', $socialLinks);
        }

        return [
            'name' => $name,
            'website' => $url,
            'email' => $email,
            'phone' => $phone,
            'city' => $city,
            'province' => $province,
            'source_url' => $url,
            'source_type' => 'website',
            'prospect_type' => 'community',
            'prospect_subtype' => $this->inferSubtype($title.' '.$text),
            'notes' => null,
            'notes_fragments' => $notesFragments,
            'contact_url' => $contactUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>|null  $secondary
     * @return array<string, mixed>
     */
    private function mergeData(array $primary, ?array $secondary): array
    {
        if ($secondary === null) {
            return $primary;
        }

        foreach (['name', 'email', 'phone', 'city', 'province', 'prospect_subtype', 'notes'] as $field) {
            if (blank($primary[$field] ?? null) && filled($secondary[$field] ?? null)) {
                $primary[$field] = $secondary[$field];
            }
        }

        if ($secondary['notes'] ?? null) {
            $primary['notes_fragments'] = array_merge($primary['notes_fragments'] ?? [], $secondary['notes_fragments'] ?? [], [$secondary['notes']]);
        }

        return $primary;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }

    private function title(DOMXPath $xpath): ?string
    {
        $node = $xpath->query('//title')->item(0);

        if (! $node) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? '') ?? '');

        return $text === '' ? null : Str::before($text, ' | ');
    }

    private function firstNodeText(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)->item(0);

        if (! $node) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? '') ?? '');

        return $text === '' ? null : $text;
    }

    private function meta(DOMXPath $xpath, string $attribute, string $value): ?string
    {
        $node = $xpath->query(sprintf('//meta[@%s="%s"]', $attribute, $value))->item(0);

        if (! $node) {
            return null;
        }

        $content = trim((string) $node->attributes?->getNamedItem('content')?->nodeValue);

        return $content === '' ? null : $content;
    }

    private function mailto(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//a[starts-with(translate(@href, "MAILTO", "mailto"), "mailto:")]') as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $email = trim(Str::after($href, 'mailto:'));

            if ($email !== '') {
                return $email;
            }
        }

        return null;
    }

    private function tel(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//a[starts-with(translate(@href, "TEL", "tel"), "tel:")]') as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $phone = trim(Str::after($href, 'tel:'));

            if ($phone !== '') {
                return $phone;
            }
        }

        return null;
    }

    private function regexMatch(string $pattern, string $value): ?string
    {
        if (preg_match($pattern, $value, $matches) !== 1) {
            return null;
        }

        return trim((string) ($matches[0] ?? '')) ?: null;
    }

    private function provinceFromText(string $text): ?string
    {
        $provinces = [
            'Drenthe', 'Flevoland', 'Friesland', 'Gelderland', 'Groningen', 'Limburg', 'Noord-Brabant',
            'Noord-Holland', 'Overijssel', 'Utrecht', 'Zeeland', 'Zuid-Holland',
        ];

        foreach ($provinces as $province) {
            if (Str::contains($text, $province)) {
                return $province;
            }
        }

        return null;
    }

    private function contactUrl(DOMXPath $xpath, string $baseUrl): ?string
    {
        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $label = trim(preg_replace('/\s+/', ' ', $node->textContent ?? '') ?? '');

            if ($href === '') {
                continue;
            }

            $haystack = Str::lower($href.' '.$label);

            if (! preg_match('/contact|over-ons|about|team|bereik|contacteer/', $haystack)) {
                continue;
            }

            $resolved = $this->resolveUrl($href, $baseUrl);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function socialLinks(DOMXPath $xpath, string $baseUrl): array
    {
        $socials = [];
        $hosts = ['instagram.com', 'facebook.com', 'linkedin.com', 'x.com', 'twitter.com', 'youtube.com', 'tiktok.com'];

        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $resolved = $this->resolveUrl($href, $baseUrl);

            if ($resolved === null) {
                continue;
            }

            $host = Str::lower((string) parse_url($resolved, PHP_URL_HOST));

            foreach ($hosts as $knownHost) {
                if (Str::contains($host, $knownHost)) {
                    $socials[] = $resolved;
                    break;
                }
            }
        }

        return array_values(array_unique($socials));
    }

    private function resolveUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);

        if ($href === '' || Str::startsWith($href, ['mailto:', 'tel:', 'javascript:', '#'])) {
            return null;
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return rtrim($href, '/');
        }

        $base = parse_url($baseUrl);

        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            return rtrim($base['scheme'].':'.$href, '/');
        }

        if (Str::startsWith($href, '/')) {
            return rtrim($base['scheme'].'://'.$base['host'].(! empty($base['port']) ? ':'.$base['port'] : '').$href, '/');
        }

        $path = rtrim((string) ($base['path'] ?? ''), '/');
        $directory = $path === '' ? '' : Str::beforeLast($path, '/');
        $prefix = $base['scheme'].'://'.$base['host'].(! empty($base['port']) ? ':'.$base['port'] : '');

        return rtrim($prefix.'/'.ltrim($directory.'/'.$href, '/'), '/');
    }

    private function inferSubtype(string $text): ?string
    {
        $text = Str::lower($text);

        return match (true) {
            str_contains($text, 'oldtimer') => 'oldtimer_club',
            str_contains($text, 'brand club') || str_contains($text, 'merkclub') => 'brand_club',
            str_contains($text, 'motorclub') || str_contains($text, 'motorcycle') => 'motorcycle_club',
            str_contains($text, 'camper') => 'camper_club',
            str_contains($text, 'youngtimer') => 'youngtimer_club',
            str_contains($text, 'trackday') => 'trackday_community',
            str_contains($text, 'forum') => 'forum',
            str_contains($text, 'stichting') || str_contains($text, 'foundation') => 'foundation',
            str_contains($text, 'vereniging') || str_contains($text, 'association') => 'association',
            default => null,
        };
    }
}
