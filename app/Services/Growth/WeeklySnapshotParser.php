<?php

namespace App\Services\Growth;

class WeeklySnapshotParser
{
    public const PARSER_VERSION = '1.0';

    public const DEFINITION_VERSION = ActivationCohortService::DEFINITION_VERSION;

    private const LABELS = [
        'totaal gebruikers' => ['core_users', 'count'],
        'core users' => ['core_users', 'count'],
        'users met voertuig' => ['users_with_vehicle', 'count'],
        'users met minimaal 1 onderhoudslog' => ['users_with_first_log', 'count'],
        'users met minimaal één onderhoudslog' => ['users_with_first_log', 'count'],
        'users met ≥2 onderhoudslogs' => ['users_with_second_log', 'count'],
        'registratie → voertuig' => ['registration_to_vehicle', 'percentage'],
        'registratie → eerste onderhoudslog' => ['registration_to_first_log', 'percentage'],
        'voertuig → eerste onderhoudslog' => ['vehicle_to_first_log', 'percentage'],
        'eerste onderhoudslog → tweede onderhoudslog' => ['first_to_second_log', 'percentage'],
        'eerste log ≤24 uur' => ['first_log_within_24h', 'percentage'],
        'eerste log ≤7 dagen' => ['first_log_within_7d', 'percentage'],
        'eerste log ≤30 dagen' => ['first_log_within_30d', 'percentage'],
        'timing eligible' => ['timing_eligible', 'count'],
        'timing excluded' => ['timing_excluded', 'count'],
    ];

    /**
     * @return array{metrics: list<array<string, mixed>>, warnings: list<string>, status: string}
     */
    public function parse(string $rawText): array
    {
        $metrics = [];
        $warnings = [];

        foreach (preg_split('/\R/u', $rawText) ?: [] as $lineNumber => $line) {
            $line = trim(preg_replace('/^[\s\-*•]+/u', '', $line));

            if ($line === '' || preg_match('/^#{1,6}\s*/', $line)) {
                continue;
            }

            if (! str_contains($line, ':')) {
                $warnings[] = 'Regel '.($lineNumber + 1).' niet herkend: '.$line;

                continue;
            }

            [$rawLabel, $rawValue] = array_map('trim', explode(':', $line, 2));
            $definition = self::LABELS[mb_strtolower($rawLabel)] ?? null;

            if ($definition === null) {
                $warnings[] = 'Regel '.($lineNumber + 1).' onbekend label: '.$rawLabel;

                continue;
            }

            [$metricKey, $unit] = $definition;
            $numerator = null;
            $denominator = null;

            if ($unit === 'percentage' && ! preg_match('/-?\d+(?:[.,]\d+)?\s*%/u', $rawValue)) {
                $warnings[] = 'Regel '.($lineNumber + 1).' bevat geen geldig percentage: '.$line;

                continue;
            }

            if (preg_match('/(?:\(|\b)(\d+)\s*(?:\/|van)\s*(\d+)(?:\)|\b)/iu', $rawValue, $matches)) {
                $numerator = (int) $matches[1];
                $denominator = (int) $matches[2];
            }

            preg_match('/-?\d+(?:[.,]\d+)?/', $rawValue, $numberMatch);
            $value = isset($numberMatch[0]) ? (float) str_replace(',', '.', $numberMatch[0]) : null;

            if ($value === null) {
                $warnings[] = 'Regel '.($lineNumber + 1).' bevat geen numerieke waarde: '.$line;

                continue;
            }

            if ($unit === 'percentage' && $numerator !== null && $denominator !== null && $denominator > 0) {
                $calculated = round(($numerator / $denominator) * 100, 1);

                if (abs($calculated - $value) > 0.1) {
                    $warnings[] = 'Regel '.($lineNumber + 1).' percentage past niet bij teller/noemer: '.$line;
                }
            }

            $metrics[$metricKey] = [
                'metric_key' => $metricKey,
                'value_numeric' => $value,
                'numerator' => $numerator,
                'denominator' => $denominator,
                'unit' => $unit,
                'definition_version' => self::DEFINITION_VERSION,
                'raw_label' => $rawLabel,
            ];
        }

        return [
            'metrics' => array_values($metrics),
            'warnings' => $warnings,
            'status' => $metrics === [] ? 'failed' : ($warnings === [] ? 'parsed' : 'parsed_with_warnings'),
        ];
    }
}
