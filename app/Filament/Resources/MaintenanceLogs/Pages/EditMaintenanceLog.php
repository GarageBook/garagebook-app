<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Support\ImageThumbnail;
use App\Support\MediaPath;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class EditMaintenanceLog extends EditRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function afterSave(): void
    {
        OptimizeMaintenanceLogMedia::dispatch($this->record->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Onderhoud verwijderen')
                ->modalHeading('Onderhoud verwijderen')
                ->modalDescription('Dit verwijdert het volledige onderhoudsitem. Media kun je hieronder per bestand verwijderen.'),
        ];
    }

    public function getHeading(): string | HtmlString
    {
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
            return 'Onderhoud bewerken';
        }

        $html = '<div style="display:flex; flex-direction:column; gap:18px;">';

        if ($images !== []) {
            $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap;">';

            foreach ($images as $image) {
                $escapedUrl = e($image['full']);
                $escapedThumbnailUrl = e($image['thumbnail']);

                $html .= '
                    <a href="' . $escapedUrl . '" target="_blank" rel="noopener noreferrer">
                        <img
                            src="' . $escapedThumbnailUrl . '"
                            alt="Onderhoudsfoto"
                            loading="lazy"
                            decoding="async"
                            width="120"
                            height="120"
                            style="width:120px; height:120px; object-fit:cover; border-radius:12px; border:1px solid #e5e7eb;"
                        >
                    </a>
                ';
            }

            $html .= '</div>';
        }

        if ($videos !== []) {
            $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap;">';

            foreach ($videos as $video) {
                $html .= '
                    <div style="display:flex; flex-direction:column; gap:8px; width:180px;">
                        <video
                            controls
                            preload="metadata"
                            playsinline
                            style="width:180px; height:120px; object-fit:cover; border-radius:12px; background:#111827;"
                        >
                            <source src="' . e($video['url']) . '">
                        </video>
                        <a
                            href="' . e($video['url']) . '"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="font-size:13px; color:#111827; text-decoration:underline;"
                        >
                            ' . $video['label'] . '
                        </a>
                    </div>
                ';
            }

            $html .= '</div>';
        }

        if ($files !== []) {
            $html .= '<div style="display:flex; flex-direction:column; gap:8px;">';
            $html .= '<div style="font-weight:700;">Overige bestanden</div>';

            foreach ($files as $file) {
                $html .= '
                    <a
                        href="' . e($file['url']) . '"
                        target="_blank"
                        rel="noopener noreferrer"
                        style="color:#111827; text-decoration:underline;"
                    >
                        ' . $file['label'] . '
                    </a>
                ';
            }

            $html .= '</div>';
        }

        $html .= '<div>Onderhoud bewerken</div>';
        $html .= '</div>';

        return new HtmlString($html);
    }
}
