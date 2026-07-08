<?php

namespace App\Services\Gsc;

class GscCsvTypeDetector
{
    public const PAGES = 'pages';

    public const QUERIES = 'queries';

    public const COUNTRIES = 'countries';

    public const DEVICES = 'devices';

    public const SEARCH_APPEARANCE = 'search_appearance';

    public const DATES = 'dates';

    public const FILTERS = 'filters';

    public const UNKNOWN = 'unknown';

    public function __construct(
        private readonly GscCsvNormalizer $normalizer,
    ) {}

    public function detect(string $path, ?string $filename = null): string
    {
        $normalized = $this->headers($path);
        $name = $filename ? $this->normalize($filename) : $this->normalize(basename($path));

        if ($this->hasMetricColumns($normalized)) {
            return match (true) {
                $this->hasAny($normalized, ['zoekopdracht', 'query']) => self::QUERIES,
                $this->hasAny($normalized, ['pagina', 'page']) => self::PAGES,
                $this->hasAny($normalized, ['land', 'country']) => self::COUNTRIES,
                $this->hasAny($normalized, ['apparaat', 'device']) => self::DEVICES,
                $this->hasAny($normalized, ['zoekopmaak', 'search appearance', 'search_appearance']) => self::SEARCH_APPEARANCE,
                $this->hasAny($normalized, ['datum', 'date']) => self::DATES,
                default => self::UNKNOWN,
            };
        }

        if (str_contains($name, 'filter')) {
            return self::FILTERS;
        }

        return self::UNKNOWN;
    }

    /**
     * @return list<string>
     */
    public function headers(string $path): array
    {
        return $this->normalizer->headers($path);
    }

    private function hasMetricColumns(array $headers): bool
    {
        return $this->hasAny($headers, ['klikken', 'clicks'])
            && $this->hasAny($headers, ['vertoningen', 'impressions'])
            && in_array('ctr', $headers, true)
            && $this->hasAny($headers, ['positie', 'position']);
    }

    private function hasAny(array $headers, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $headers, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return $this->normalizer->normalizeHeader($value);
    }
}
