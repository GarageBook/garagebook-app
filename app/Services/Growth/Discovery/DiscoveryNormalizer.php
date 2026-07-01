<?php

namespace App\Services\Growth\Discovery;

use Illuminate\Support\Str;

class DiscoveryNormalizer
{
    public function normalizeText(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return $value === '' ? null : $value;
    }

    public function normalizeNotes(mixed $value): ?string
    {
        return $this->normalizeText($value);
    }

    public function normalizeName(mixed $value): ?string
    {
        return $this->normalizeText($value);
    }

    public function normalizeWebsite(mixed $value): ?string
    {
        $value = $this->normalizeUrl($value);

        return $value;
    }

    public function normalizeEmail(mixed $value): ?string
    {
        $value = Str::lower((string) $this->normalizeText($value));

        return $value === '' ? null : $value;
    }

    public function normalizePhone(mixed $value): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $value) ?: '';

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '+31')) {
            $normalized = '0'.substr($normalized, 3);
        } elseif (str_starts_with($normalized, '0031')) {
            $normalized = '0'.substr($normalized, 4);
        }

        return $normalized;
    }

    public function normalizeUrl(mixed $value): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            return null;
        }

        if (! Str::startsWith($value, ['http://', 'https://'])) {
            $value = 'https://'.$value;
        }

        return rtrim($value, '/');
    }

    public function nameFromUrl(mixed $value): ?string
    {
        $value = $this->normalizeUrl($value);

        if ($value === null) {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return Str::headline(Str::before($host, '.'));
    }

    public function nameFromEmail(mixed $value): ?string
    {
        $email = $this->normalizeEmail($value);

        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        return Str::headline(Str::before($email, '@'));
    }

    public function inferProspectSubtype(string $text): ?string
    {
        $text = Str::lower($text);

        $patterns = [
            'oldtimer_club' => ['oldtimer', 'classic car club', 'classic club'],
            'brand_club' => ['brand club', 'merkclub'],
            'motorcycle_club' => ['motorclub', 'motorcycle club', 'motorclub', 'motorrijder'],
            'car_club' => ['car club', 'autoclub'],
            'camper_club' => ['camperclub', 'camper club', 'campervereniging'],
            'youngtimer_club' => ['youngtimer', 'youngtimer club'],
            'trackday_community' => ['trackday', 'track day'],
            'forum' => ['forum'],
            'foundation' => ['foundation', 'stichting'],
            'association' => ['association', 'vereniging'],
            'tire_specialist' => ['banden', 'bandenspecialist', 'tire'],
            'detailing' => ['detailing', 'carclean', 'poetsbedrijf'],
            'tuning' => ['tuning', 'tuner', 'performance'],
            'suspension' => ['vering', 'suspension', 'onderstel'],
            'brakes' => ['remmen', 'brakes', 'remspecialist'],
            'parts_webshop' => ['onderdelen', 'parts', 'webshop'],
            'motorcycle_accessories' => ['motoraccessoires', 'motor accessoires', 'motorbike accessories'],
            'oldtimer_restoration' => ['oldtimerrestauratie', 'restauratie', 'classic restoration'],
            'custom_shop' => ['custom shop', 'custom', 'special build'],
            'camper_specialist' => ['camper', 'camperbedrijf', 'camper service'],
            '4x4_specialist' => ['4x4', 'offroad', 'four wheel drive'],
        ];

        foreach ($patterns as $subtype => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $subtype;
                }
            }
        }

        return null;
    }
}
