<?php

namespace App\Data\Growth;

use App\Services\Growth\Discovery\DiscoveryNormalizer;

final class DiscoveryResult
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $website = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $city = null,
        public readonly ?string $province = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $sourceType = null,
        public readonly ?string $prospectType = 'community',
        public readonly ?string $prospectSubtype = null,
        public readonly ?string $notes = null,
        public readonly int $qualityScore = 0,
        public readonly array $qualityFlags = [],
        public readonly string $qualityVerdict = 'accepted',
        public readonly ?string $qualityReason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $fallbackSourceType = null): self
    {
        $normalizer = app(DiscoveryNormalizer::class);
        $name = $normalizer->normalizeName($data['name'] ?? null)
            ?? $normalizer->nameFromUrl($data['website'] ?? $data['source_url'] ?? null)
            ?? $normalizer->nameFromEmail($data['email'] ?? null)
            ?? null;
        $website = $normalizer->normalizeWebsite($data['website'] ?? null);
        $email = $normalizer->normalizeEmail($data['email'] ?? null);
        $phone = $normalizer->normalizePhone($data['phone'] ?? null);
        $city = $normalizer->normalizeText($data['city'] ?? null);
        $province = $normalizer->normalizeText($data['province'] ?? null);
        $sourceUrl = $normalizer->normalizeUrl($data['source_url'] ?? null);
        $sourceType = $normalizer->normalizeText($data['source_type'] ?? null) ?? $fallbackSourceType;
        $notes = $normalizer->normalizeNotes($data['notes'] ?? null);
        $prospectType = $normalizer->normalizeText($data['prospect_type'] ?? null) ?: 'community';
        $prospectSubtype = $normalizer->normalizeText($data['prospect_subtype'] ?? null)
            ?? $normalizer->inferProspectSubtype(implode(' ', array_filter([
                $name,
                $website,
                $email,
                $city,
                $province,
                $notes,
                $sourceUrl,
                $sourceType,
            ])));

        return new self(
            name: $name,
            website: $website,
            email: $email,
            phone: $phone,
            city: $city,
            province: $province,
            sourceUrl: $sourceUrl,
            sourceType: $sourceType,
            prospectType: $prospectType,
            prospectSubtype: $prospectSubtype,
            notes: $notes,
            qualityScore: (int) ($data['quality_score'] ?? 0),
            qualityFlags: self::normalizeQualityFlags($data['quality_flags'] ?? []),
            qualityVerdict: self::normalizeQualityVerdict($data['quality_verdict'] ?? 'accepted'),
            qualityReason: $normalizer->normalizeText($data['quality_reason'] ?? null),
        );
    }

    public function withQuality(int $score, array $flags, string $verdict, ?string $reason = null): self
    {
        return new self(
            name: $this->name,
            website: $this->website,
            email: $this->email,
            phone: $this->phone,
            city: $this->city,
            province: $this->province,
            sourceUrl: $this->sourceUrl,
            sourceType: $this->sourceType,
            prospectType: $this->prospectType,
            prospectSubtype: $this->prospectSubtype,
            notes: $this->notes,
            qualityScore: max(0, min(100, $score)),
            qualityFlags: array_values(array_unique(array_filter($flags, static fn (mixed $flag): bool => is_string($flag) && trim($flag) !== ''))),
            qualityVerdict: $verdict,
            qualityReason: $reason,
        );
    }

    public function dedupeKey(): string
    {
        return strtolower(trim(implode('|', array_filter([
            $this->email ?: null,
            $this->website ?: null,
            $this->sourceUrl ?: null,
            $this->name ?: null,
            $this->phone ?: null,
        ]))));
    }

    public function mergeWith(self $other): self
    {
        return new self(
            name: $this->name ?: $other->name,
            website: $this->website ?: $other->website,
            email: $this->email ?: $other->email,
            phone: $this->phone ?: $other->phone,
            city: $this->city ?: $other->city,
            province: $this->province ?: $other->province,
            sourceUrl: $this->sourceUrl ?: $other->sourceUrl,
            sourceType: $this->sourceType ?: $other->sourceType,
            prospectType: $this->prospectType ?: $other->prospectType,
            prospectSubtype: $this->prospectSubtype ?: $other->prospectSubtype,
            notes: $this->notes ?: $other->notes,
            qualityScore: max($this->qualityScore, $other->qualityScore),
            qualityFlags: array_values(array_unique(array_filter(array_merge($this->qualityFlags, $other->qualityFlags)))),
            qualityVerdict: $this->qualityVerdict !== 'accepted' ? $this->qualityVerdict : $other->qualityVerdict,
            qualityReason: $this->qualityReason ?: $other->qualityReason,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toCsvRow(): array
    {
        return [
            'name' => (string) ($this->name ?? ''),
            'website' => (string) ($this->website ?? ''),
            'email' => (string) ($this->email ?? ''),
            'phone' => (string) ($this->phone ?? ''),
            'city' => (string) ($this->city ?? ''),
            'province' => (string) ($this->province ?? ''),
            'source_url' => (string) ($this->sourceUrl ?? ''),
            'source_type' => (string) ($this->sourceType ?? ''),
            'prospect_type' => (string) ($this->prospectType ?? ''),
            'prospect_subtype' => (string) ($this->prospectSubtype ?? ''),
            'notes' => (string) ($this->notes ?? ''),
            'quality_score' => (string) $this->qualityScore,
            'quality_flags' => json_encode($this->qualityFlags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'quality_verdict' => $this->qualityVerdict,
            'quality_reason' => (string) ($this->qualityReason ?? ''),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeQualityFlags(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map(static fn (mixed $flag): string => trim((string) $flag), $value))));
        }

        if (! is_string($value)) {
            return [];
        }

        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return array_values(array_unique(array_filter(array_map(static fn (mixed $flag): string => trim((string) $flag), $decoded))));
        }

        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\s*[|,;]\s*/', $value) ?: []))));
    }

    private static function normalizeQualityVerdict(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['accepted', 'manual_review', 'rejected'], true) ? $value : 'accepted';
    }
}
