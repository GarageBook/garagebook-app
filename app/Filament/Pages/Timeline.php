<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TripLogs\TripLogResource;
use App\Models\MaintenanceLog;
use App\Models\TripLog;
use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use App\Services\Outreach\OutreachDemoService;
use App\Support\ImageThumbnail;
use App\Support\MediaPath;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

class Timeline extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'tijdlijn';

    protected string $view = 'filament.pages.timeline';

    protected string | \Filament\Support\Enums\Width | null $maxContentWidth = 'full';

    #[Url(as: 'vehicle_id')]
    public ?int $activeVehicleId = null;

    public function mount(): void
    {
        $this->activeVehicleId = $this->resolveActiveVehicleId($this->activeVehicleId);
    }

    public function updatedActiveVehicleId(): void
    {
        $this->activeVehicleId = $this->resolveActiveVehicleId($this->activeVehicleId);
    }

    public function getHeading(): string
    {
        return __('dashboard.timeline_heading');
    }

    public function getSubheading(): ?string
    {
        return __('dashboard.timeline_subheading');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.timeline_label');
    }

    protected function getViewData(): array
    {
        $vehicles = Vehicle::query()
            ->where('user_id', auth()->id())
            ->withCount('maintenanceLogs')
            ->withSum('maintenanceLogs', 'cost')
            ->latest()
            ->get();

        $activeVehicle = $vehicles->firstWhere('id', $this->activeVehicleId);
        $maintenanceEntries = collect();
        $timelineGroups = collect();
        $totalItems = 0;
        $periodLabel = null;

        if ($activeVehicle) {
            $distanceUnit = app(DistanceUnitService::class);
            $activeDistanceUnit = $distanceUnit->normalizeUnit($activeVehicle->distance_unit);

            $logs = MaintenanceLog::query()
                ->where('vehicle_id', $activeVehicle->id)
                ->orderBy('maintenance_date')
                ->orderBy('id')
                ->get();

            $trips = TripLog::query()
                ->where('vehicle_id', $activeVehicle->id)
                ->where('user_id', auth()->id())
                ->orderBy('ridden_at')
                ->orderBy('id')
                ->get();

            $maintenanceEntries = $logs->values()->map(
                fn (MaintenanceLog $log, int $index): array => $this->maintenanceEntry($log, $index, $activeDistanceUnit, $distanceUnit, $activeVehicle)
            );

            $tripEntries = $trips->values()->map(
                fn (TripLog $trip): array => $this->tripEntry($trip)
            );

            $combinedEntries = $maintenanceEntries
                ->concat($tripEntries)
                ->filter(fn (array $entry): bool => filled($entry['sortDate'] ?? null));

            $totalItems = $combinedEntries->count();
            $timelineGroups = $this->buildTimelineGroups($combinedEntries);

            if ($timelineGroups->isNotEmpty()) {
                $firstDate = $timelineGroups->first()['sortDate'] ?? null;
                $lastDate = $timelineGroups->last()['sortDate'] ?? null;

                if (is_string($firstDate) && is_string($lastDate)) {
                    $periodLabel = Carbon::parse($firstDate)->translatedFormat('M Y')
                        . ' - '
                        . Carbon::parse($lastDate)->translatedFormat('M Y');
                }
            }
        }

        return [
            'vehicles' => $vehicles,
            'activeVehicle' => $activeVehicle,
            'entries' => $maintenanceEntries->values()->all(),
            'entriesJson' => json_encode($maintenanceEntries->values()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timelineGroups' => $timelineGroups->all(),
            'totalItems' => $totalItems,
            'periodLabel' => $periodLabel,
            'showDemoIntro' => app(OutreachDemoService::class)->shouldShowDemoIntroForAuthenticatedUser(),
            'demoIntroDismissUrl' => route('outreach.demo.intro-dismiss'),
            'totalCostLabel' => $activeVehicle
                ? __('dashboard.timeline.currency_prefix') . ' ' . number_format((float) ($activeVehicle->maintenance_logs_sum_cost ?? 0), 2, ',', '.')
                : __('dashboard.timeline.currency_prefix') . ' 0,00',
        ];
    }

    protected function resolveActiveVehicleId(?int $vehicleId): ?int
    {
        $vehicles = Vehicle::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get(['id']);

        if ($vehicles->isEmpty()) {
            return null;
        }

        if ($vehicleId && $vehicles->contains('id', $vehicleId)) {
            return $vehicleId;
        }

        $latestMaintenanceVehicleId = MaintenanceLog::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->value('vehicle_id');

        if ($latestMaintenanceVehicleId && $vehicles->contains('id', $latestMaintenanceVehicleId)) {
            return $latestMaintenanceVehicleId;
        }

        return $vehicles->first()->id;
    }

    private function maintenanceEntry(
        MaintenanceLog $log,
        int $index,
        string $activeDistanceUnit,
        DistanceUnitService $distanceUnit,
        Vehicle $activeVehicle,
    ): array {
        $images = collect($log->attachments)
            ->filter(fn (string $attachment) => MediaPath::isImage($attachment))
            ->map(function (string $attachment): array {
                $thumbnail = ImageThumbnail::path($attachment, 640) ?: $attachment;

                return [
                    'thumbnail' => Storage::url($thumbnail),
                    'full' => Storage::url($attachment),
                    'label' => MediaPath::label($attachment),
                ];
            })
            ->values()
            ->all();

        $files = collect($log->attachments)
            ->filter(fn (string $attachment) => ! MediaPath::isImage($attachment))
            ->map(fn (string $attachment): array => [
                'label' => MediaPath::label($attachment),
                'type' => MediaPath::isVideo($attachment)
                    ? __('dashboard.timeline.file_type_video')
                    : (MediaPath::isPdf($attachment) ? __('dashboard.timeline.file_type_pdf') : __('dashboard.timeline.file_type_file')),
                'url' => Storage::url($attachment),
            ])
            ->values()
            ->all();

        return [
            'id' => $log->id,
            'type' => 'maintenance',
            'index' => $index,
            'sortDate' => $log->maintenance_date?->toDateString(),
            'dateLabel' => $log->maintenance_date?->translatedFormat('d M Y'),
            'monthLabel' => $log->maintenance_date?->translatedFormat('M'),
            'dayLabel' => $log->maintenance_date?->format('d'),
            'title' => $log->description,
            'distanceLabel' => $distanceUnit->formatFromKilometers($log->km_reading, $activeDistanceUnit, 0),
            'costLabel' => $log->cost !== null
                ? __('dashboard.timeline.currency_prefix') . ' ' . number_format((float) $log->cost, 2, ',', '.')
                : null,
            'workedHoursLabel' => $log->worked_hours !== null
                ? rtrim(rtrim(number_format((float) $log->worked_hours, 2, ',', '.'), '0'), ',') . ' ' . __('dashboard.timeline.worked_hours_suffix')
                : null,
            'notes' => $log->notes,
            'images' => $images,
            'files' => $files,
            'previewImage' => $images[0]['thumbnail'] ?? null,
            'imageCount' => count($images),
            'fileCount' => count($files),
            'fallbackBrand' => $activeVehicle->brand,
        ];
    }

    private function tripEntry(TripLog $trip): array
    {
        $previewPhotos = collect($trip->photos ?? [])
            ->filter(fn (mixed $photo) => is_string($photo) && filled($photo) && MediaPath::isImage($photo))
            ->values()
            ->take(3)
            ->map(fn (string $photo, int $index): array => [
                'url' => route('trip-photos.show', ['trip' => $trip, 'photoIndex' => $index]),
                'label' => MediaPath::label($photo),
            ])
            ->all();

        $photoCount = count($trip->photos ?? []);

        return [
            'id' => $trip->id,
            'type' => 'trip',
            'sortDate' => $trip->ridden_at?->toDateString(),
            'title' => $trip->title ?: __('trips.table.no_title'),
            'label' => __('dashboard.timeline.trips_item_label'),
            'dateLabel' => $trip->ridden_at?->translatedFormat('d F Y'),
            'riddenLabel' => $trip->ridden_at
                ? __('dashboard.timeline.trips_ridden_on', ['date' => $trip->ridden_at->translatedFormat('d F Y')])
                : null,
            'distanceLabel' => $trip->distance_km !== null
                ? number_format((float) $trip->distance_km, 2, ',', '.') . ' km'
                : null,
            'metaLabel' => filled($trip->source_file_name)
                ? __('dashboard.timeline.trips_meta_uploaded')
                : null,
            'photoCountLabel' => $photoCount > 0
                ? trans_choice('dashboard.timeline.images_count', min($photoCount, 3), ['count' => min($photoCount, 3)])
                : null,
            'previewPhotos' => $previewPhotos,
            'tripUrl' => TripLogResource::getUrl('view', ['record' => $trip]),
        ];
    }

    private function buildTimelineGroups($combinedEntries)
    {
        $previousYear = null;

        return $combinedEntries
            ->groupBy('sortDate')
            ->sortKeys()
            ->values()
            ->map(function ($items) use (&$previousYear): array {
                $firstItem = $items->first();
                $sortDate = $firstItem['sortDate'];
                $date = Carbon::parse($sortDate);
                $year = $date->format('Y');

                $group = [
                    'sortDate' => $sortDate,
                    'showYearMarker' => $year !== $previousYear,
                    'year' => $year,
                    'maintenanceItems' => $items->where('type', 'maintenance')->values()->all(),
                    'tripItems' => $items->where('type', 'trip')->values()->all(),
                ];

                $previousYear = $year;

                return $group;
            });
    }
}
