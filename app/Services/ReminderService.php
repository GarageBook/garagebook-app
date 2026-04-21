<?php

namespace App\Services;

use App\Models\MaintenanceLog;
use Carbon\Carbon;

class ReminderService
{
    public function getStatus(MaintenanceLog $log): array
    {
        if (!$log->reminder_enabled) {
            return ['type' => 'none', 'text' => null];
        }

        $now = now();

        // DATE
        if ($log->interval_months && $log->last_date) {
            $nextDate = Carbon::parse($log->last_date)->addMonths($log->interval_months);

            if ($now->greaterThan($nextDate)) {
                return [
                    'type' => 'overdue',
                    'text' => $nextDate->diffForHumans($now, ['parts' => 2, 'short' => true]) . ' te laat',
                ];
            }

            return [
                'type' => 'upcoming',
                'text' => $now->diffForHumans($nextDate, ['parts' => 2, 'short' => true]),
            ];
        }

        // KM
        if ($log->interval_km && $log->last_km) {
            $currentKm = $log->vehicle->current_km ?? 0;
            $remaining = ($log->last_km + $log->interval_km) - $currentKm;

            if ($remaining <= 0) {
                return [
                    'type' => 'overdue',
                    'text' => abs($remaining) . ' km te laat',
                ];
            }

            return [
                'type' => 'upcoming',
                'text' => $remaining . ' km te gaan',
            ];
        }

        return ['type' => 'none', 'text' => null];
    }

    public function getWidgetItems(int $limit = 10): array
    {
        return MaintenanceLog::query()
            ->where('reminder_enabled', true)
            ->where(function ($query) {
                $query
                    ->whereNotNull('interval_months')
                    ->orWhereNotNull('interval_km');
            })
            ->with('vehicle')
            ->latest('maintenance_date')
            ->get()
            ->map(function (MaintenanceLog $log) {
                return [
                    'log' => $log,
                    'status' => $this->getStatus($log),
                ];
            })
            ->filter(fn (array $item) => $item['status']['type'] !== 'none')
            ->take($limit)
            ->values()
            ->all();
    }
}
