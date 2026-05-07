<?php

namespace App\Services;

use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ReminderService
{
    public function __construct(
        private readonly DistanceUnitService $distanceUnitService
    ) {}

    public function getStatus(MaintenanceLog $log): array
    {
        if (! $log->reminder_enabled) {
            return ['type' => 'none', 'text' => null];
        }

        $now = now();
        $dateStatus = $this->getDateStatus($log, $now);
        $kmStatus = $this->getKmStatus($log);

        if ($dateStatus === null && $kmStatus === null) {
            return ['type' => 'none', 'text' => null];
        }

        $type = collect([$dateStatus['type'] ?? null, $kmStatus['type'] ?? null])->contains('overdue')
            ? 'overdue'
            : 'upcoming';

        $parts = array_values(array_filter([
            $dateStatus['label'] ?? null,
            $kmStatus['label'] ?? null,
        ]));

        return [
            'type' => $type,
            'heading' => $this->shortDescription($log->description),
            'text' => $this->buildSentence($type, $parts),
            'priority' => $this->resolvePriority($dateStatus, $kmStatus),
        ];
    }

    public function getWidgetItems(int $limit = 10, ?int $userId = null): array
    {
        $userId ??= auth()->id();

        if ($userId === null) {
            return [];
        }

        $vehicleIds = Vehicle::query()
            ->where('user_id', $userId)
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            return [];
        }

        return MaintenanceLog::query()
            ->whereIn('vehicle_id', $vehicleIds)
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
            ->sortBy([
                fn (array $item) => $item['status']['type'] === 'overdue' ? 0 : 1,
                fn (array $item) => $item['status']['priority'] ?? PHP_INT_MAX,
                fn (array $item) => $item['log']->maintenance_date?->timestamp ?? PHP_INT_MAX,
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    private function getDateStatus(MaintenanceLog $log, Carbon $now): ?array
    {
        if (! $log->interval_months || ! $log->last_date) {
            return null;
        }

        $nextDate = Carbon::parse($log->last_date)->addMonths($log->interval_months);

        if ($now->greaterThan($nextDate)) {
            return [
                'type' => 'overdue',
                'label' => $nextDate->diffForHumans($now, [
                    'parts' => 2,
                    'short' => false,
                    'syntax' => Carbon::DIFF_ABSOLUTE,
                ]) . ' te laat',
                'priority' => -1 * $nextDate->diffInDays($now),
            ];
        }

        return [
            'type' => 'upcoming',
            'label' => 'over ' . $now->diffForHumans($nextDate, [
                'parts' => 2,
                'short' => false,
                'syntax' => Carbon::DIFF_ABSOLUTE,
            ]),
            'priority' => $now->diffInDays($nextDate),
        ];
    }

    private function getKmStatus(MaintenanceLog $log): ?array
    {
        if (! $log->interval_km || ! $log->last_km) {
            return null;
        }

        $currentKm = $log->vehicle->current_km ?? 0;
        $remaining = (int) (($log->last_km + $log->interval_km) - $currentKm);
        $remainingLabel = $this->distanceUnitService->formatFromKilometers(abs($remaining), $log->vehicle?->distance_unit, 0);

        if ($remaining <= 0) {
            return [
                'type' => 'overdue',
                'label' => $remainingLabel . ' te laat',
                'priority' => $remaining,
            ];
        }

        return [
            'type' => 'upcoming',
            'label' => $this->distanceUnitService->formatFromKilometers($remaining, $log->vehicle?->distance_unit, 0),
            'priority' => $remaining,
        ];
    }

    private function resolvePriority(?array $dateStatus, ?array $kmStatus): int
    {
        $priorities = array_values(array_filter([
            $dateStatus['priority'] ?? null,
            $kmStatus['priority'] ?? null,
        ], fn (mixed $value) => is_int($value)));

        if ($priorities === []) {
            return PHP_INT_MAX;
        }

        return min($priorities);
    }

    private function shortDescription(?string $description): string
    {
        $description = trim((string) $description);

        if ($description === '') {
            return 'onderhoud';
        }

        $description = preg_replace('/\s+/', ' ', $description) ?? $description;

        return Str::limit($description, 42, '...');
    }

    private function buildSentence(string $type, array $parts): string
    {
        if ($parts === []) {
            return '';
        }

        $sentence = implode(' of ', $parts);

        if ($type === 'upcoming') {
            return ucfirst($sentence) . '.';
        }

        return ucfirst($sentence) . '.';
    }
}
