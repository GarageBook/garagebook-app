<?php

namespace App\Services\Growth;

use App\Models\GrowthProspect;
use App\Services\Growth\Discovery\DiscoveryNormalizer;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Community2026EnrichmentService
{
    private const AUTO_CONFIDENCE = 90;

    private const PREFERRED_PREFIXES = [
        'info' => 100,
        'secretariaat' => 90,
        'secretaris' => 88,
        'contact' => 80,
        'verkoop' => 76,
        'service' => 75,
        'support' => 74,
        'bestuur' => 70,
        'algemeen' => 60,
        'vereniging' => 58,
        'club' => 56,
        'webmaster' => 50,
        'redactie' => 45,
        'pr' => 40,
        'admin' => 30,
        'mail' => 25,
    ];

    private const BLOCKED_PREFIX_PATTERNS = [
        '/no-?reply/i',
        '/do-?not-?reply/i',
        '/noreply/i',
        '/ledenadministratie/i',
        '/webshop/i',
        '/shop/i',
        '/bestelling/i',
        '/order/i',
        '/sales/i',
    ];

    private const LINK_KEYWORDS = [
        'contact', 'contactpagina', 'contacteer', 'contact-us', 'bestuur', 'secretariaat', 'secretaris', 'vereniging',
        'clubinfo', 'club-info', 'organisatie', 'over-ons', 'over ons', 'about', 'service', 'klantenservice', 'winkel', 'shop', 'privacy', 'privacybeleid',
        'privacy-statement', 'algemene-voorwaarden', 'voorwaarden',
    ];

    private const STANDARD_PATHS = [
        '/contact',
        '/bestuur',
        '/secretariaat',
        '/privacy',
        '/algemene-voorwaarden',
        '/vereniging',
        '/contactpagina',
        '/clubinfo',
        '/club-info',
        '/organisatie',
        '/over-ons',
        '/about',
        '/service',
        '/klantenservice',
        '/winkel',
        '/shop',
        '/onderdelen',
        '/producten',
        '/privacybeleid',
        '/privacy-statement',
        '/voorwaarden',
    ];

    public function __construct(
        private readonly Community2026ImportService $importer,
        private readonly DiscoveryNormalizer $normalizer,
        private readonly GrowthProspectNormalizer $prospectNormalizer,
    ) {}

    /**
     * @return array{scanned:int,auto_found:int,suggested_found:int,still_missing:int,ready_top_50:array<int, array{name:string,website:?string,email:?string,confidence:?int}>}
     */
    public function enrich(?int $limit = null): array
    {
        $summary = [
            'scanned' => 0,
            'auto_found' => 0,
            'suggested_found' => 0,
            'still_missing' => 0,
            'ready_top_50' => [],
        ];

        $query = $this->eligibleProspectsQuery()->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $query->get()->each(function (GrowthProspect $prospect) use (&$summary): void {
            $summary['scanned']++;
            $candidate = $this->findBestEmail($prospect);

            if ($candidate === null) {
                $prospect->forceFill([
                    'enrichment_notes' => $this->appendNote($prospect, $this->importer->campaignSlug().'_enrichment: geen bruikbaar publiek e-mailadres gevonden'),
                ])->save();

                return;
            }

            if ($candidate['confidence'] >= self::AUTO_CONFIDENCE) {
                $prospect->forceFill([
                    'email' => $candidate['email'],
                    'normalized_email' => $this->prospectNormalizer->normalizeEmail($candidate['email']),
                    'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
                    'verification_required' => false,
                    'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                    'status' => GrowthProspect::LIFECYCLE_READY,
                    'skip_reason' => null,
                    'suggested_email' => $candidate['email'],
                    'suggested_email_confidence' => $candidate['confidence'],
                    'suggested_email_source_url' => $candidate['source_url'],
                    'enrichment_notes' => $this->appendNote($prospect, $candidate['note']),
                ])->save();
                $summary['auto_found']++;

                return;
            }

            $prospect->forceFill([
                'suggested_email' => $candidate['email'],
                'suggested_email_confidence' => $candidate['confidence'],
                'suggested_email_source_url' => $candidate['source_url'],
                'verification_required' => true,
                'skip_reason' => 'missing_email',
                'enrichment_notes' => $this->appendNote($prospect, $candidate['note']),
            ])->save();
            $summary['suggested_found']++;
        });

        $summary['still_missing'] = $this->missingWithoutSuggestionQuery()->count();
        $summary['ready_top_50'] = $this->readyTop50();

        return $summary;
    }

    private function eligibleProspectsQuery(): Builder
    {
        $campaign = $this->importer->campaign();

        return GrowthProspect::query()
            ->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)
            ->where('verification_required', true)
            ->whereNull('suggested_email')
            ->whereNull('enrichment_notes')
            ->where('lifecycle_status', '!=', GrowthProspect::LIFECYCLE_ARCHIVED)
            ->where(function (Builder $query) use ($campaign): void {
                $query->where('campaign_id', $campaign->id)
                    ->orWhere('last_campaign_slug', $this->importer->campaignSlug());
            });
    }

    private function missingWithoutSuggestionQuery(): Builder
    {
        $campaign = $this->importer->campaign();

        return GrowthProspect::query()
            ->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)
            ->whereNull('suggested_email')
            ->where('lifecycle_status', '!=', GrowthProspect::LIFECYCLE_ARCHIVED)
            ->where(function (Builder $query) use ($campaign): void {
                $query->where('campaign_id', $campaign->id)
                    ->orWhere('last_campaign_slug', $this->importer->campaignSlug());
            });
    }

    /**
     * @return array{email:string,confidence:int,source_url:string,note:string}|null
     */
    private function findBestEmail(GrowthProspect $prospect): ?array
    {
        $website = $this->normalizer->normalizeWebsite($prospect->website);

        if ($website === null) {
            return null;
        }

        $candidates = [];
        $home = $this->fetchPage($website);

        if ($home !== null) {
            $candidates = array_merge($candidates, $this->emailCandidates($home));
            $best = $this->bestCandidate($candidates);

            if (($best['confidence'] ?? 0) >= self::AUTO_CONFIDENCE) {
                return $best;
            }
        }

        foreach ($this->candidateUrls($website, $home) as $url) {
            if ($url === $website) {
                continue;
            }

            $page = $this->fetchPage($url);

            if ($page === null) {
                continue;
            }

            $candidates = array_merge($candidates, $this->emailCandidates($page));
            $best = $this->bestCandidate($candidates);

            if (($best['confidence'] ?? 0) >= self::AUTO_CONFIDENCE) {
                return $best;
            }
        }

        return $this->bestCandidate($candidates);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{email:string,confidence:int,source_url:string,prefix_score:int,note:string}|null
     */
    private function bestCandidate(array $candidates): ?array
    {
        $candidates = array_values(array_filter(
            $candidates,
            fn (array $candidate): bool => ! $this->isBlockedEmail((string) ($candidate['email'] ?? '')),
        ));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b): int {
            return [$b['prefix_score'], $b['confidence'], $a['email']] <=> [$a['prefix_score'], $a['confidence'], $b['email']];
        });

        $best = $candidates[0];
        $best['note'] = sprintf(
            '%s_enrichment: %s gevonden met confidence %d via %s',
            $this->importer->campaignSlug(),
            $best['email'],
            $best['confidence'],
            $best['source_url'],
        );

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private function candidateUrls(string $website, ?array $home): array
    {
        $urls = [$website];
        $base = parse_url($website);

        if (is_array($base) && isset($base['scheme'], $base['host'])) {
            $root = $base['scheme'].'://'.$base['host'].(! empty($base['port']) ? ':'.$base['port'] : '');

            foreach (self::STANDARD_PATHS as $path) {
                $urls[] = $root.$path;
            }
        }

        foreach (($home['links'] ?? []) as $link) {
            $href = (string) ($link['href'] ?? '');
            $label = Str::lower((string) ($link['label'] ?? ''));
            $haystack = Str::lower($href.' '.$label);

            if (! collect(self::LINK_KEYWORDS)->contains(fn (string $keyword): bool => Str::contains($haystack, $keyword))) {
                continue;
            }

            $resolved = $this->resolveUrl($href, $website);

            if ($resolved !== null) {
                $urls[] = $resolved;
            }
        }

        $host = parse_url($website, PHP_URL_HOST);

        $urls = array_values(array_unique(array_filter($urls, function (string $url) use ($host): bool {
            return parse_url($url, PHP_URL_HOST) === $host;
        })));

        return array_slice($urls, 0, 6);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPage(string $url): ?array
    {
        try {
            $response = Http::connectTimeout(0.5)
                ->timeout(0.5)
                ->withHeaders(['Accept' => 'text/html'])
                ->get($url);
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

        return $this->extractPage($body, $url);
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
        $footerText = $this->footerText($xpath);
        $links = [];

        foreach ($xpath->query('//a[@href]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $links[] = [
                'href' => trim((string) $node->getAttribute('href')),
                'label' => trim(preg_replace('/\s+/', ' ', $node->textContent ?? '') ?? ''),
            ];
        }

        return [
            'url' => $url,
            'html' => $html,
            'text' => $text,
            'footer_text' => $footerText,
            'links' => $links,
            'page_type' => $this->pageType($url, $text),
            'mailto_emails' => $this->mailtoEmails($xpath),
            'text_emails' => $this->textEmails($html.' '.$text),
            'footer_emails' => $this->textEmails($footerText),
        ];
    }

    /**
     * @return array<int, array{email:string,confidence:int,source_url:string,prefix_score:int}>
     */
    private function emailCandidates(array $page): array
    {
        $candidates = [];
        $url = (string) $page['url'];
        $pageType = (string) $page['page_type'];

        foreach ($page['mailto_emails'] as $email) {
            $candidates[] = $this->candidate($email, 95, $url);
        }

        foreach ($page['footer_emails'] as $email) {
            $candidates[] = $this->candidate($email, 85, $url);
        }

        $textConfidence = match ($pageType) {
            'contact' => 90,
            'privacy' => 80,
            default => 70,
        };

        foreach ($page['text_emails'] as $email) {
            $candidates[] = $this->candidate($email, $textConfidence, $url);
        }

        $deduped = [];

        foreach ($candidates as $candidate) {
            $email = $candidate['email'];

            if (! isset($deduped[$email]) || $candidate['confidence'] > $deduped[$email]['confidence']) {
                $deduped[$email] = $candidate;
            }
        }

        return array_values($deduped);
    }

    /**
     * @return array{email:string,confidence:int,source_url:string,prefix_score:int}
     */
    private function candidate(string $email, int $confidence, string $sourceUrl): array
    {
        $email = (string) $this->prospectNormalizer->normalizeEmail($email);
        $prefix = Str::before($email, '@');

        return [
            'email' => $email,
            'confidence' => $confidence,
            'source_url' => $sourceUrl,
            'prefix_score' => self::PREFERRED_PREFIXES[$prefix] ?? 1,
        ];
    }

    private function isBlockedEmail(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return true;
        }

        $prefix = Str::before($email, '@');

        foreach (self::BLOCKED_PREFIX_PATTERNS as $pattern) {
            if (preg_match($pattern, $prefix) === 1) {
                return true;
            }
        }

        if (isset(self::PREFERRED_PREFIXES[$prefix])) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function mailtoEmails(DOMXPath $xpath): array
    {
        $emails = [];

        foreach ($xpath->query('//a[starts-with(translate(@href, "MAILTO", "mailto"), "mailto:")]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $email = Str::before(Str::after($node->getAttribute('href'), 'mailto:'), '?');
            $email = $this->prospectNormalizer->normalizeEmail($email);

            if ($email !== null) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array<int, string>
     */
    private function textEmails(string $value): array
    {
        if (preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value, $matches) < 1) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $email): ?string => $this->prospectNormalizer->normalizeEmail($email),
            $matches[0] ?? [],
        ))));
    }

    private function footerText(DOMXPath $xpath): string
    {
        $parts = [];

        foreach ($xpath->query('//footer') as $node) {
            $parts[] = $node->textContent ?? '';
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
    }

    private function pageType(string $url, string $text): string
    {
        $url = Str::lower($url);
        $haystack = Str::lower($url.' '.$text);

        if (Str::contains($url, ['privacy', 'algemene-voorwaarden', 'voorwaarden'])) {
            return 'privacy';
        }

        if (Str::contains($url, ['contact', 'bestuur', 'secretariaat', 'secretaris', 'organisatie', 'vereniging', 'clubinfo'])) {
            return 'contact';
        }

        if (Str::contains($haystack, ['contact', 'bestuur', 'secretariaat', 'secretaris', 'organisatie', 'vereniging', 'clubinfo'])) {
            return 'contact';
        }

        return 'text';
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

        return rtrim($base['scheme'].'://'.$base['host'].'/'.ltrim($href, '/'), '/');
    }

    private function appendNote(GrowthProspect $prospect, string $note): string
    {
        $notes = array_filter([
            trim((string) $prospect->enrichment_notes),
            $note,
        ]);

        return implode(' | ', array_values(array_unique($notes)));
    }

    /**
     * @return array<int, array{name:string,website:?string,email:?string,confidence:?int}>
     */
    private function readyTop50(): array
    {
        $campaign = $this->importer->campaign();

        return GrowthProspect::query()
            ->where('campaign_id', $campaign->id)
            ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
            ->whereNotNull('email')
            ->orderByDesc('suggested_email_confidence')
            ->orderBy('name')
            ->limit(50)
            ->get(['name', 'website', 'email', 'suggested_email_confidence'])
            ->map(fn (GrowthProspect $prospect): array => [
                'name' => $prospect->name,
                'website' => $prospect->website,
                'email' => $prospect->email,
                'confidence' => $prospect->suggested_email_confidence,
            ])
            ->all();
    }
}
