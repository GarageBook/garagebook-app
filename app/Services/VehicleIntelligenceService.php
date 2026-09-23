<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates real data from vehicles + maintenance_logs for a given brand/model.
 * Never fabricates information. Returns null/empty for unavailable fields so
 * the view can conditionally hide sections.
 */
class VehicleIntelligenceService
{
    private const POWERTRAIN_LABELS = [
        'petrol' => 'Benzine',
        'diesel' => 'Diesel',
        'hybrid' => 'Hybride',
        'phev' => 'Plug-in hybride',
        'electric' => 'Elektrisch',
        'lpg' => 'LPG',
        'other' => 'Overig',
    ];

    /**
     * @return array{
     *   specifications: array{min_year: ?int, max_year: ?int, year_range: ?string, powertrains: list<string>, powertrain_labels: list<string>},
     *   common_maintenance: Collection,
     *   garage_book_stats: array{total_logs: int, oldest_log_date: ?string, avg_logs_per_vehicle: float},
     * }
     */
    public function forBrandModel(string $brand, string $model): array
    {
        return [
            'specifications' => $this->specifications($brand, $model),
            'common_maintenance' => $this->commonMaintenance($brand, $model),
            'garage_book_stats' => $this->garageBookStats($brand, $model),
            // known_issues: no dedicated data source exists; always empty
            'known_issues' => [],
        ];
    }

    public function flushForBrandModel(string $brand, string $model): void
    {
        // Kept for callers; authority intelligence is resolved directly.
    }

    /**
     * @return array{min_year: ?int, max_year: ?int, year_range: ?string, powertrains: list<string>, powertrain_labels: list<string>}
     */
    private function specifications(string $brand, string $model): array
    {
        $base = $this->publicVehicleQuery($brand, $model);

        $row = (clone $base)
            ->selectRaw('MIN(vehicles.year) as min_year, MAX(vehicles.year) as max_year')
            ->first();

        $powertrains = (clone $base)
            ->whereNotNull('vehicles.powertrain_type')
            ->distinct()
            ->orderBy('vehicles.powertrain_type')
            ->pluck('vehicles.powertrain_type')
            ->filter()
            ->values()
            ->all();

        $minYear = $row->min_year ? (int) $row->min_year : null;
        $maxYear = $row->max_year ? (int) $row->max_year : null;

        $yearRange = match (true) {
            $minYear === null => null,
            $minYear === $maxYear => (string) $minYear,
            default => "{$minYear}–{$maxYear}",
        };

        $powertrain_labels = array_values(array_filter(
            array_map(fn ($t) => self::POWERTRAIN_LABELS[$t] ?? null, $powertrains)
        ));

        return [
            'min_year' => $minYear,
            'max_year' => $maxYear,
            'year_range' => $yearRange,
            'powertrains' => $powertrains,
            'powertrain_labels' => $powertrain_labels,
        ];
    }

    /**
     * Top maintenance descriptions by frequency across all public vehicles.
     * Returns at most 10 items. Empty collection when no logs exist.
     */
    private function commonMaintenance(string $brand, string $model): Collection
    {
        return DB::table('maintenance_logs')
            ->join('vehicles', 'maintenance_logs.vehicle_id', '=', 'vehicles.id')
            ->join('users', 'vehicles.user_id', '=', 'users.id')
            ->where('vehicles.brand', $brand)
            ->where('vehicles.model', $model)
            ->where('vehicles.is_public', true)
            ->where('users.is_outreach_demo', false)
            ->whereNotNull('maintenance_logs.description')
            ->where('maintenance_logs.description', '!=', '')
            ->groupBy('maintenance_logs.description')
            ->selectRaw('maintenance_logs.description, COUNT(*) as frequency')
            ->orderByDesc('frequency')
            ->limit(10)
            ->get();
    }

    /**
     * @return array{total_logs: int, oldest_log_date: ?string, avg_logs_per_vehicle: float}
     */
    private function garageBookStats(string $brand, string $model): array
    {
        $stats = DB::table('maintenance_logs')
            ->join('vehicles', 'maintenance_logs.vehicle_id', '=', 'vehicles.id')
            ->join('users', 'vehicles.user_id', '=', 'users.id')
            ->where('vehicles.brand', $brand)
            ->where('vehicles.model', $model)
            ->where('vehicles.is_public', true)
            ->where('users.is_outreach_demo', false)
            ->selectRaw('COUNT(*) as total_logs, MIN(maintenance_logs.maintenance_date) as oldest_log')
            ->first();

        $totalLogs = (int) ($stats->total_logs ?? 0);

        $publicCount = $this->publicVehicleQuery($brand, $model)
            ->selectRaw('COUNT(*) as cnt')
            ->value('cnt');

        $publicCount = (int) ($publicCount ?? 0);

        return [
            'total_logs' => $totalLogs,
            'oldest_log_date' => $stats->oldest_log ?? null,
            'avg_logs_per_vehicle' => $publicCount > 0 ? round($totalLogs / $publicCount, 1) : 0.0,
        ];
    }

    private function publicVehicleQuery(string $brand, string $model): Builder
    {
        return DB::table('vehicles')
            ->join('users', 'vehicles.user_id', '=', 'users.id')
            ->where('vehicles.brand', $brand)
            ->where('vehicles.model', $model)
            ->where('vehicles.is_public', true)
            ->where('users.is_outreach_demo', false)
            ->whereNotNull('vehicles.public_slug')
            ->where('vehicles.public_slug', '!=', '');
    }
}
