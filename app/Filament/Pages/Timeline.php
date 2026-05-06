<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use App\Support\ImageThumbnail;
use App\Support\MediaPath;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

class Timeline extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Tijdlijn';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'tijdlijn';

    protected string $view = 'filament.pages.timeline';

    protected string | \Filament\Support\Enums\Width | null $maxContentWidth = 'full';

    #[Url(as: 'vehicle_id')]
    public ?int $activeVehicleId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

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
        return 'Tijdlijn';
    }

    public function getSubheading(): ?string
    {
        return 'Een visueel overzicht van het onderhoud van je voertuig, in chronologische volgorde.';
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

        $logs = collect();

        if ($activeVehicle) {
            $logs = MaintenanceLog::query()
                ->where('vehicle_id', $activeVehicle->id)
                ->orderBy('maintenance_date')
                ->orderBy('id')
                ->get();
        }

        $entries = [];
        $previousYear = null;

        foreach ($logs as $index => $log) {
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
                        ? 'Video'
                        : (MediaPath::isPdf($attachment) ? 'PDF' : 'Bestand'),
                    'url' => Storage::url($attachment),
                ])
                ->values()
                ->all();

            $year = $log->maintenance_date?->format('Y');

            $entries[] = [
                'id' => $log->id,
                'index' => $index,
                'side' => $index % 2 === 0 ? 'top' : 'bottom',
                'showYearMarker' => $year !== $previousYear,
                'year' => $year,
                'dateLabel' => $log->maintenance_date?->translatedFormat('d M Y'),
                'monthLabel' => $log->maintenance_date?->translatedFormat('M'),
                'dayLabel' => $log->maintenance_date?->format('d'),
                'title' => $log->description,
                'kmLabel' => number_format($log->km_reading ?? 0, 0, ',', '.'),
                'costLabel' => $log->cost !== null
                    ? 'EUR ' . number_format((float) $log->cost, 2, ',', '.')
                    : null,
                'workedHoursLabel' => $log->worked_hours !== null
                    ? rtrim(rtrim(number_format((float) $log->worked_hours, 2, ',', '.'), '0'), ',') . ' uur'
                    : null,
                'notes' => $log->notes,
                'images' => $images,
                'files' => $files,
                'previewImage' => $images[0]['thumbnail'] ?? null,
                'imageCount' => count($images),
                'fileCount' => count($files),
            ];

            $previousYear = $year;
        }

        $periodLabel = null;

        if ($logs->isNotEmpty()) {
            $periodLabel = $logs->first()->maintenance_date?->translatedFormat('M Y')
                . ' - '
                . $logs->last()->maintenance_date?->translatedFormat('M Y');
        }

        return [
            'vehicles' => $vehicles,
            'activeVehicle' => $activeVehicle,
            'entries' => $entries,
            'entriesJson' => json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'periodLabel' => $periodLabel,
            'totalCostLabel' => $activeVehicle
                ? 'EUR ' . number_format((float) ($activeVehicle->maintenance_logs_sum_cost ?? 0), 2, ',', '.')
                : 'EUR 0,00',
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

        return $vehicles->first()->id;
    }
}
