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
        $headers = $this->headers($path);
        $profile = $this->normalizer->profile($headers);
        $name = $filename ? $this->normalize($filename) : $this->normalize(basename($path));

        if ($profile['missing_required'] === []) {
            $type = $this->typeFromDimensions($profile['dimension_candidates']);

            if ($type !== self::UNKNOWN) {
                return $type;
            }
        }

        if (str_contains($name, 'filter')) {
            return self::FILTERS;
        }

        if ($profile['missing_required'] === []) {
            return match (true) {
                str_contains($name, 'pagina') || str_contains($name, 'page') => self::PAGES,
                str_contains($name, 'zoekopdracht') || str_contains($name, 'query') => self::QUERIES,
                str_contains($name, 'land') || str_contains($name, 'countr') => self::COUNTRIES,
                str_contains($name, 'apparaat') || str_contains($name, 'device') => self::DEVICES,
                str_contains($name, 'zoekopmaak') || str_contains($name, 'appearance') => self::SEARCH_APPEARANCE,
                str_contains($name, 'diagram') || str_contains($name, 'date') || str_contains($name, 'datum') => self::DATES,
                default => self::UNKNOWN,
            };
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

    /**
     * @param  list<string>  $dimensions
     */
    private function typeFromDimensions(array $dimensions): string
    {
        foreach ([
            'queries' => self::QUERIES,
            'pages' => self::PAGES,
            'countries' => self::COUNTRIES,
            'devices' => self::DEVICES,
            'search_appearance' => self::SEARCH_APPEARANCE,
            'dates' => self::DATES,
        ] as $dimension => $type) {
            if (in_array($dimension, $dimensions, true)) {
                return $type;
            }
        }

        return self::UNKNOWN;
    }

    private function normalize(string $value): string
    {
        return $this->normalizer->normalizeHeader($value);
    }
}
