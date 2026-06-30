<?php

namespace App\Services\Growth;

use App\Models\GrowthProspect;
use App\Services\Growth\Discovery\DiscoveryNormalizer;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Community2026AttentionCrawler
{
    public function __construct(
        private readonly DiscoveryNormalizer $normalizer,
    ) {}

    /**
     * @return array{suggested_email:?string, contact_url:?string, notes:?string}
     */
    public function crawl(GrowthProspect $record): array
    {
        $website = $this->normalizer->normalizeWebsite($record->website);

        if ($website === null) {
            return [
                'suggested_email' => null,
                'contact_url' => null,
                'notes' => 'no_website',
            ];
        }

        $pages = [];
        $main = $this->fetchPage($website);

        if ($main !== null) {
            $pages[] = $main;
        }

        foreach ($this->candidateUrls($main ?? [], $website) as $candidateUrl) {
            $page = $this->fetchPage($candidateUrl);

            if ($page !== null) {
                $pages[] = $page;
            }
        }

        $emails = [];
        $contactUrl = null;
        $notes = [];

        foreach ($pages as $page) {
            if ($contactUrl === null && $page['is_contact_page']) {
                $contactUrl = $page['url'];
            }

            foreach ($page['emails'] as $emailInfo) {
                $emails[] = $emailInfo;
            }

            if (! empty($page['signals'])) {
                $notes = array_merge($notes, $page['signals']);
            }

            if ($contactUrl === null && $page['page_contact_url'] !== null) {
                $contactUrl = $page['page_contact_url'];
            }
        }

        $suggestedEmail = $this->chooseEmail($emails);

        if ($contactUrl === null) {
            $contactUrl = $this->chooseContactUrl($pages);
        }

        $notes = array_values(array_unique(array_filter($notes)));

        if ($suggestedEmail === null && $emails !== []) {
            $notes[] = 'emails_found: '.implode(', ', array_values(array_unique(array_map(fn ($email) => $email['email'], $emails))));
        }

        if ($suggestedEmail !== null) {
            $notes[] = 'suggested_email: '.$suggestedEmail;
        }

        return [
            'suggested_email' => $suggestedEmail,
            'contact_url' => $contactUrl,
            'notes' => trim(implode(' | ', $notes)) ?: null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function candidateUrls(array $page, string $website): array
    {
        $candidates = [];

        foreach (($page['links'] ?? []) as $link) {
            $href = (string) ($link['href'] ?? '');
            $label = Str::lower(trim((string) ($link['label'] ?? '')));
            $haystack = Str::lower($href.' '.$label);

            if (! preg_match('/contact|contacteer|contact-us|contactpagina|bestuur|secretariaat|vereniging|organisatie|over-ons|about|team|privacy/', $haystack)) {
                continue;
            }

            $resolved = $this->resolveUrl($href, $website);

            if ($resolved !== null) {
                $candidates[] = $resolved;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPage(string $url): ?array
    {
        try {
            $response = Http::timeout(15)->withHeaders(['Accept' => 'text/html'])->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->extractPage((string) $response->body(), $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPage(string $html, string $url): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $text = trim(preg_replace('/\s+/', ' ', $document->textContent ?? '') ?? '');
        $title = $this->title($xpath) ?? '';
        $pageTitle = Str::lower($title.' '.$text);
        $isContactPage = (bool) preg_match('/contact|contacteer|contactpagina|bestuur|secretariaat|privacy|over-ons|about|team|vereniging|organisatie/', $pageTitle);
        $emails = $this->emails($xpath, $html, $text);
        $links = $this->links($xpath, $url);
        $signals = [];
        $pageContactUrl = null;

        if ($isContactPage) {
            $signals[] = 'contact_page: '.$url;
            $pageContactUrl = $url;
        }

        if ($emails !== []) {
            $signals[] = 'emails: '.implode(', ', array_values(array_unique(array_map(fn ($item) => $item['email'], $emails))));
        }

        foreach ($links as $link) {
            if (($link['is_social'] ?? false) === true) {
                $signals[] = 'social: '.$link['href'];
            }
        }

        return [
            'url' => $url,
            'title' => $title,
            'text' => $text,
            'emails' => $emails,
            'links' => $links,
            'signals' => $signals,
            'is_contact_page' => $isContactPage,
            'page_contact_url' => $pageContactUrl,
        ];
    }

    /**
     * @return array<int, array{email:string, score:int}>
     */
    private function emails(DOMXPath $xpath, string $html, string $text): array
    {
        $emails = [];

        foreach ($xpath->query('//a[starts-with(translate(@href, "MAILTO", "mailto"), "mailto:")]') as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $email = $this->normalizer->normalizeEmail(Str::after($href, 'mailto:'));

            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[] = ['email' => $email, 'score' => 4];
            }
        }

        if (preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $html.' '.$text, $matches) === 1) {
            foreach (array_unique($matches[0] ?? []) as $match) {
                $email = $this->normalizer->normalizeEmail($match);

                if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $emails[] = ['email' => $email, 'score' => 2];
                }
            }
        }

        return array_values($emails);
    }

    /**
     * @return array<int, array{href:string,label:string,is_social:bool}>
     */
    private function links(DOMXPath $xpath, string $baseUrl): array
    {
        $links = [];
        $hosts = ['instagram.com', 'facebook.com', 'linkedin.com', 'x.com', 'twitter.com', 'youtube.com', 'tiktok.com'];

        foreach ($xpath->query('//a[@href]') as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $label = trim(preg_replace('/\s+/', ' ', $node->textContent ?? '') ?? '');
            $resolved = $this->resolveUrl($href, $baseUrl);

            if ($resolved === null) {
                continue;
            }

            $host = Str::lower((string) parse_url($resolved, PHP_URL_HOST));
            $isSocial = false;

            foreach ($hosts as $knownHost) {
                if (Str::contains($host, $knownHost)) {
                    $isSocial = true;
                    break;
                }
            }

            $links[] = [
                'href' => $resolved,
                'label' => $label,
                'is_social' => $isSocial,
            ];
        }

        return array_values(array_unique($links, SORT_REGULAR));
    }

    /**
     * @param  array<int, array<string, mixed>>  $emails
     */
    private function chooseEmail(array $emails): ?string
    {
        if ($emails === []) {
            return null;
        }

        $scored = [];

        foreach ($emails as $emailInfo) {
            $email = (string) ($emailInfo['email'] ?? '');
            $prefix = Str::before($email, '@');
            $score = (int) ($emailInfo['score'] ?? 0);

            if (in_array($prefix, ['info', 'contact', 'secretariaat', 'secretary', 'webmaster', 'bestuur', 'pr', 'vereniging'], true)) {
                $score += 2;
            }

            $scored[$email] = max($scored[$email] ?? 0, $score);
        }

        arsort($scored);

        $bestEmail = array_key_first($scored);
        $bestScore = $bestEmail !== null ? $scored[$bestEmail] : 0;
        $secondScore = array_values($scored)[1] ?? 0;

        if ($bestEmail === null || $bestScore < 4 || $bestScore === $secondScore) {
            return null;
        }

        return $bestEmail;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    private function chooseContactUrl(array $pages): ?string
    {
        foreach ($pages as $page) {
            if (! empty($page['is_contact_page'])) {
                return (string) $page['url'];
            }
        }

        return $pages[0]['url'] ?? null;
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
}
