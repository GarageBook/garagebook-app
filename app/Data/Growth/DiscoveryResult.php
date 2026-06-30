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
        ];
    }
}
