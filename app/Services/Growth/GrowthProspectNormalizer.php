<?php

namespace App\Services\Growth;

use App\Models\GrowthProspect;
use Illuminate\Support\Str;

class GrowthProspectNormalizer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizePayload(array $data): array
    {
        $name = $this->clean($data['name'] ?? null);
        $website = $this->normalizeWebsite($data['website'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        $domain = $this->normalizeDomain($website ?: ($data['website'] ?? null));
        $organizationKey = $this->organizationKey($name, $domain);
        $emailStatus = $this->emailStatus($email, $data['email_status'] ?? null);
        $prospectType = $this->clean($data['prospect_type'] ?? null) ?: 'community';
        $prospectSubtype = $this->clean($data['prospect_subtype'] ?? null);

        $verificationRequired = $this->verificationRequired($emailStatus, $website, $data);
        $lifecycleStatus = $this->lifecycleStatus($emailStatus, $verificationRequired);

        return array_filter([
            'name' => $name,
            'website' => $website,
            'organization_key' => $organizationKey,
            'normalized_domain' => $domain,
            'email' => $email,
            'normalized_email' => $email,
            'email_status' => $emailStatus,
            'verification_required' => $verificationRequired,
            'phone' => $this->normalizePhone($data['phone'] ?? null),
            'city' => $this->clean($data['city'] ?? null),
            'region' => $this->clean($data['city'] ?? null) ?: $this->clean($data['region'] ?? null),
            'prospect_type' => in_array($prospectType, GrowthProspect::PROSPECT_TYPES, true) ? $prospectType : 'community',
            'prospect_subtype' => in_array($prospectSubtype, GrowthProspect::PROSPECT_SUBTYPES, true) ? $prospectSubtype : $prospectSubtype,
            'category' => $prospectType ?: 'community',
            'subcategory' => $prospectSubtype,
            'source_url' => $this->clean($data['source_url'] ?? null),
            'source_type' => $this->clean($data['source_type'] ?? null),
            'notes' => $this->clean($data['notes'] ?? null),
            'skip_reason' => $this->skipReason($emailStatus),
            'lifecycle_status' => $lifecycleStatus,
            'status' => $lifecycleStatus,
        ], fn ($value): bool => $value !== null);
    }

    public function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function normalizeEmail(mixed $email): ?string
    {
        $email = Str::lower((string) $this->clean($email));

        return $email === '' ? null : $email;
    }

    public function normalizeWebsite(mixed $website): ?string
    {
        $website = $this->clean($website);

        if ($website === null) {
            return null;
        }

        if (! str_contains($website, '://')) {
            $website = 'https://'.$website;
        }

        return rtrim($website, '/');
    }

    public function normalizeDomain(mixed $value): ?string
    {
        $value = $this->clean($value);

        if ($value === null) {
            return null;
        }

        if (str_contains($value, '@')) {
            $value = Str::after($value, '@');
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST) ?: $value;
        $host = Str::lower((string) $host);
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return trim($host, '/') ?: null;
    }

    public function organizationKey(?string $name, ?string $domain): ?string
    {
        if ($domain) {
            return $domain;
        }

        if (! $name) {
            return null;
        }

        return Str::slug(Str::lower($name)) ?: null;
    }

    public function normalizePhone(mixed $phone): ?string
    {
        $phone = $this->clean($phone);

        if ($phone === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $phone);

        return $normalized ?: null;
    }

    private function emailStatus(?string $email, mixed $explicitStatus): string
    {
        $explicitStatus = $this->clean($explicitStatus);

        if ($explicitStatus && in_array($explicitStatus, GrowthProspect::EMAIL_STATUSES, true)) {
            return $explicitStatus;
        }

        if ($email === null) {
            return GrowthProspect::EMAIL_STATUS_MISSING;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false
            ? GrowthProspect::EMAIL_STATUS_INVALID
            : GrowthProspect::EMAIL_STATUS_FOUND;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function verificationRequired(string $emailStatus, ?string $website, array $data): bool
    {
        if (in_array($emailStatus, [GrowthProspect::EMAIL_STATUS_MISSING, GrowthProspect::EMAIL_STATUS_INVALID], true)) {
            return true;
        }

        return blank($website)
            || blank($data['source_url'] ?? null);
    }

    private function lifecycleStatus(string $emailStatus, bool $verificationRequired): string
    {
        return match ($emailStatus) {
            GrowthProspect::EMAIL_STATUS_MISSING => GrowthProspect::LIFECYCLE_ENRICHED,
            GrowthProspect::EMAIL_STATUS_INVALID => GrowthProspect::LIFECYCLE_MANUAL_REVIEW,
            GrowthProspect::EMAIL_STATUS_VERIFIED, GrowthProspect::EMAIL_STATUS_FOUND => $verificationRequired ? GrowthProspect::LIFECYCLE_ENRICHED : GrowthProspect::LIFECYCLE_READY,
            default => GrowthProspect::LIFECYCLE_ENRICHED,
        };
    }

    private function skipReason(string $emailStatus): ?string
    {
        return match ($emailStatus) {
            GrowthProspect::EMAIL_STATUS_MISSING => 'missing_email',
            GrowthProspect::EMAIL_STATUS_INVALID => 'invalid_email',
            default => null,
        };
    }
}
