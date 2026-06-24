<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsDataWindow
{
    /**
     * @return array{
     *     has_table: bool,
     *     has_data: bool,
     *     start_date: ?string,
     *     end_date: ?string,
     *     start_at: ?string,
     *     end_at: ?string,
     *     is_stale: bool,
     *     label: ?string,
     *     warning: ?string
     * }
     */
    public static function forTable(string $table, string $column = 'date', int $days = 30): array
    {
        if (! Schema::hasTable($table)) {
            return self::emptyRange(hasTable: false);
        }

        $maxDate = DB::table($table)->max($column);

        if (! $maxDate) {
            return self::emptyRange(hasTable: true);
        }

        $endDate = Carbon::parse($maxDate)->startOfDay();
        $startDate = $endDate->copy()->subDays(max(1, $days) - 1)->startOfDay();
        $isStale = $endDate->copy()->endOfDay()->lt(now()->subHours(48));

        return [
            'has_table' => true,
            'has_data' => true,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'start_at' => $startDate->startOfDay()->toDateTimeString(),
            'end_at' => $endDate->endOfDay()->toDateTimeString(),
            'is_stale' => $isStale,
            'label' => 'Data t/m '.$endDate->translatedFormat('d-m-Y'),
            'warning' => $isStale ? 'Analytics-sync lijkt achter te lopen' : null,
        ];
    }

    /**
     * @return array{
     *     has_table: bool,
     *     has_data: bool,
     *     start_date: ?string,
     *     end_date: ?string,
     *     start_at: ?string,
     *     end_at: ?string,
     *     is_stale: bool,
     *     label: ?string,
     *     warning: ?string
     * }
     */
    private static function emptyRange(bool $hasTable): array
    {
        return [
            'has_table' => $hasTable,
            'has_data' => false,
            'start_date' => null,
            'end_date' => null,
            'start_at' => null,
            'end_at' => null,
            'is_stale' => false,
            'label' => null,
            'warning' => null,
        ];
    }
}
