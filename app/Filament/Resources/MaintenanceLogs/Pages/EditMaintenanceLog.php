<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Services\DistanceUnitService;
use App\Support\AnalyticsEventTracker;
use App\Support\ImageThumbnail;
use App\Support\MediaPath;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class EditMaintenanceLog extends EditRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $service = app(DistanceUnitService::class);
        $unit = $service->resolveForVehicleId($data['vehicle_id'] ?? null);

        $data['distance_unit'] = $unit;
        $data['km_reading'] = $service->fromKilometers($data['km_reading'] ?? null, $unit, 0);
        $data['interval_km'] = $service->fromKilometers($data['interval_km'] ?? null, $unit, 0);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $service = app(DistanceUnitService::class);
        $unit = $service->persistVehicleUnit(
            isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            $data['distance_unit'] ?? null
        );

        $data['km_reading'] = (int) round($service->toKilometers($data['km_reading'] ?? null, $unit, 0) ?? 0);
        $data['interval_km'] = $service->toKilometers($data['interval_km'] ?? null, $unit, 0);
        unset($data['distance_unit']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('reminder_enabled') && $this->record->reminder_enabled) {
            app(AnalyticsEventTracker::class)->queueReminderCreated($this->record->vehicle, 'maintenance');
        }

        OptimizeMaintenanceLogMedia::dispatch($this->record->getKey());
    }

    protected function getRedirectUrl(): string
    {
        return MaintenanceLogResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('maintenance.actions.delete'))
                ->modalHeading(__('maintenance.edit_page.delete_modal_heading'))
                ->modalDescription(__('maintenance.edit_page.delete_modal_description')),
        ];
    }

    public function getHeading(): string|HtmlString
    {
        $title = __('maintenance.edit_page.title');
        $otherFiles = __('maintenance.edit_page.other_files');
        $images = [];
        $videos = [];

        foreach ($this->record->media_attachments as $attachment) {
            $url = Storage::url($attachment);

            if (MediaPath::isImage($attachment)) {
                $images[] = [
                    'full' => $url,
                    'thumbnail' => Storage::url(ImageThumbnail::path($attachment, 240) ?: $attachment),
                ];

                continue;
            }

            if (MediaPath::isVideo($attachment)) {
                $videos[] = [
                    'url' => $url,
                    'label' => e(MediaPath::label($attachment)),
                ];
            }
        }

        $files = array_map(
            fn (string $attachment) => [
                'url' => Storage::url($attachment),
                'label' => e(MediaPath::label($attachment)),
            ],
            $this->record->file_attachments
        );

        if ($images === [] && $videos === [] && $files === []) {
            return $title;
        }

        $html = '<div style="display:flex; flex-direction:column; gap:18px;">';

        if ($images !== []) {
            $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap;">';

            foreach ($images as $image) {
                $escapedUrl = e($image['full']);
                $escapedThumbnailUrl = e($image['thumbnail']);

                $html .= '\n                    <a href="'.$escapedUrl.'" target="_blank" rel="noopener noreferrer">\n                        <img\n                            src="'.$escapedThumbnailUrl.'"\n                            alt="'.e(__('maintenance.edit_page.photo_alt')).'"\n                            loading="lazy"\n                            decoding="async"\n                            width="120"\n                            height="120"\n                            style="width:120px; height:120px; object-fit:cover; border-radius:12px; border:1px solid #e5e7eb;"\n                        >\n                    </a>\n                ';
            }

            $html .= '</div>';
        }

        if ($videos !== []) {
            $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap;">';

            foreach ($videos as $video) {
                $html .= '\n                    <div style="display:flex; flex-direction:column; gap:8px; width:180px;">\n                        <video\n                            controls\n                            preload="metadata"\n                            playsinline\n                            style="width:180px; height:120px; object-fit:cover; border-radius:12px; background:#111827;"\n                        >\n                            <source src="'.e($video['url']).'">\n                        </video>\n                        <a\n                            href="'.e($video['url']).'"\n                            target="_blank"\n                            rel="noopener noreferrer"\n                            style="font-size:13px; color:#111827; text-decoration:underline;"\n                        >\n                            '.$video['label'].'\n                        </a>\n                    </div>\n                ';
            }

            $html .= '</div>';
        }

        if ($files !== []) {
            $html .= '<div style="display:flex; flex-direction:column; gap:8px;">';
            $html .= '<div style="font-weight:700;">'.e($otherFiles).'</div>';

            foreach ($files as $file) {
                $html .= '\n                    <a\n                        href="'.e($file['url']).'"\n                        target="_blank"\n                        rel="noopener noreferrer"\n                        style="color:#111827; text-decoration:underline;"\n                    >\n                        '.$file['label'].'\n                    </a>\n                ';
            }

            $html .= '</div>';
        }

        $html .= '<div>'.e($title).'</div>';
        $html .= '</div>';

        return new HtmlString($html);
    }
}
