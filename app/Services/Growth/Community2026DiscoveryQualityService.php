<?php

namespace App\Services\Growth;

use App\Data\Growth\DiscoveryResult;
use App\Models\GrowthProspect;
use Illuminate\Support\Str;

class Community2026DiscoveryQualityService
{
    private const ACCEPTED_THRESHOLD = 80;

    /**
     * @return array<int, string>
     */
    private const ORGANIZATION_KEYWORDS = [
        'club', 'vereniging', 'stichting', 'foundation', 'association', 'community', 'forum', 'team',
        'owners', 'riders', 'motorclub', 'autoclub', 'car club', 'merkclub', 'oldtimer', 'youngtimer',
        'camper', 'trackday', 'circuit', 'land rover', 'jeep', 'bmw', 'volvo', 'honda', 'yamaha', 'ducati',
        'alfa', 'porsche', 'mx-5', 'mx5', 'motor', 'classic', 'klassiek', 'liefhebbers', 'freunde',
    ];

    /**
     * @return array<int, string>
     */
    private const REJECT_NAME_PATTERNS = [
        '/\\b\\d{1,2}\\s*(?:januari|februari|maart|april|mei|juni|juli|augustus|september|oktober|november|december)\\b/i',
        '/\\b\\d{4}\\b/',
        '/\\b(?:agenda|evenement|event|nieuws|bericht|dag|goedgekeurd|uitslag|report|verslag)\\b/i',
    ];

    /**
     * @return array<int, string>
     */
    private const PLACEHOLDER_NAMES = [
        'home', 'welkom', 'welkom!', 'start', 'index', 'contact', 'privacy', 'over ons', 'about', 'team',
        'kickstart', 'lees meer', 'klik hier', 'doe mee', 'doe mee aan evenementen', 'evenementen', 'nieuws',
        'goed geconserveerde grootvader',
    ];

    public function assess(DiscoveryResult $result): DiscoveryResult
    {
        $score = 100;
        $flags = [];
        $name = Str::lower(trim((string) ($result->name ?? '')));
        $website = $result->website;
        $email = $result->email;
        $subtype = $result->prospectSubtype;
        $hasWebsite = filled($website);
        $hasEmail = filled($email);
        $hasOrganizationSignal = $this->hasOrganizationSignal($name, (string) ($result->notes ?? ''));

        if (! $hasWebsite && ! $hasEmail) {
            return $result->withQuality(0, ['missing_website_email'], 'rejected', 'missing website and email');
        }

        if ($subtype !== null && ! in_array($subtype, GrowthProspect::PROSPECT_SUBTYPES, true)) {
            return $result->withQuality(0, ['invalid_subtype'], 'rejected', 'invalid prospect subtype');
        }

        if ($hasWebsite && Str::contains((string) ($result->notes ?? ''), 'Community2026 seed URL')) {
            return $result->withQuality(60, ['seed_url_fallback', 'no_email', 'manual_review_required'], 'manual_review', 'seed URL fallback needs enrichment');
        }

        if ($this->looksLikeEventTitle($name, $hasOrganizationSignal)) {
            return $result->withQuality(0, ['event_title'], 'rejected', 'looks like an event or article title');
        }

        if ($this->looksLikePlaceholder($name, $hasOrganizationSignal)) {
            return $result->withQuality(0, ['placeholder_name'], 'rejected', 'placeholder or generic title');
        }

        if ($this->looksLikeGibberish($name)) {
            return $result->withQuality(0, ['gibberish_name'], 'rejected', 'gibberish or malformed title');
        }

        if (! $hasWebsite) {
            $score -= 24;
            $flags[] = 'no_website';
        }

        if (! $hasEmail) {
            $score -= 18;
            $flags[] = 'no_email';
        }

        if ($subtype === null) {
            $score -= 8;
            $flags[] = 'missing_subtype';
        }

        if (! $hasOrganizationSignal) {
            $score -= 14;
            $flags[] = 'low_organization_signal';
        }

        if ($hasWebsite && ! $this->hasPathOrDomainSignal($website)) {
            $score -= 3;
            $flags[] = 'weak_website_signal';
        }

        $score = max(0, min(100, $score));

        $verdict = $score >= self::ACCEPTED_THRESHOLD && $hasWebsite && $hasEmail && $subtype !== null
            ? 'accepted'
            : 'manual_review';

        $reason = $this->reasonForVerdict($verdict, $flags, $hasWebsite, $hasEmail, $subtype);

        if ($verdict === 'accepted') {
            $flags[] = 'ready';
        } else {
            $flags[] = 'manual_review_required';
        }

        return $result->withQuality($score, $flags, $verdict, $reason);
    }

    private function looksLikeEventTitle(string $name, bool $hasOrganizationSignal): bool
    {
        if ($name === '') {
            return false;
        }

        foreach (self::REJECT_NAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1 && ! $hasOrganizationSignal) {
                return true;
            }
        }

        if (! $hasOrganizationSignal && preg_match('/\\b(?:goedgekeurd|aangekondigd|verslag|report|nieuws|agenda|evenement)\\b/i', $name) === 1) {
            return true;
        }

        return false;
    }

    private function looksLikePlaceholder(string $name, bool $hasOrganizationSignal): bool
    {
        if ($name === '') {
            return true;
        }

        foreach (self::PLACEHOLDER_NAMES as $placeholder) {
            if ($name === $placeholder || Str::contains($name, $placeholder)) {
                return ! $hasOrganizationSignal;
            }
        }

        if (! $hasOrganizationSignal && preg_match('/^[\\p{L}\\d\\s!?.-]{1,20}$/u', $name) === 1) {
            return true;
        }

        return false;
    }

    private function looksLikeGibberish(string $name): bool
    {
        if ($name === '') {
            return true;
        }

        $letters = preg_replace('/[^\\p{L}]/u', '', $name) ?: '';
        $symbols = preg_replace('/[\\p{L}\\d\\s]/u', '', $name) ?: '';

        if (mb_strlen($letters) < 4) {
            return true;
        }

        if (mb_strlen($symbols) >= 4) {
            return true;
        }

        return preg_match('/^(.)\\1{3,}$/u', $name) === 1;
    }

    private function hasOrganizationSignal(string $name, string $notes): bool
    {
        $haystack = Str::lower($name.' '.$notes);

        foreach (self::ORGANIZATION_KEYWORDS as $keyword) {
            if (Str::contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function hasPathOrDomainSignal(?string $website): bool
    {
        if ($website === null) {
            return false;
        }

        $path = (string) parse_url($website, PHP_URL_PATH);

        return $path !== '' && $path !== '/';
    }

    /**
     * @param  array<int, string>  $flags
     */
    private function reasonForVerdict(string $verdict, array $flags, bool $hasWebsite, bool $hasEmail, ?string $subtype): ?string
    {
        if ($verdict === 'accepted') {
            return null;
        }

        if (! $hasWebsite && ! $hasEmail) {
            return 'missing website and email';
        }

        if (! $hasWebsite) {
            return 'missing website';
        }

        if (! $hasEmail) {
            return 'missing email';
        }

        if ($subtype === null) {
            return 'missing subtype';
        }

        return $flags[0] ?? 'manual review required';
    }
}
