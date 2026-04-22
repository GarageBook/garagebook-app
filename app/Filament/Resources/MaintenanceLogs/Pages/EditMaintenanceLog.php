<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Support\MediaPath;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class EditMaintenanceLog extends EditRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getHeading(): string | HtmlString
    {
        $attachments = $this->record->attachments;

        if (is_string($attachments)) {
            $attachments = json_decode($attachments, true);
        }

        if (! is_array($attachments)) {
            return 'Onderhoud bewerken';
        }

        $attachments = array_values(array_filter(
            $attachments,
            fn ($attachment) => is_string($attachment) && filled($attachment)
        ));

        if ($attachments === []) {
            return 'Onderhoud bewerken';
        }

        $images = [];
        $videos = [];
        $files = [];

        foreach ($attachments as $attachment) {
            $url = Storage::url($attachment);
            $label = e(MediaPath::label($attachment));

            if (MediaPath::isImage($attachment)) {
                $images[] = $url;

                continue;
            }

            if (MediaPath::isVideo($attachment)) {
                $videos[] = [
                    'url' => $url,
                    'label' => $label,
                ];

                continue;
            }

            $files[] = [
                'url' => $url,
                'label' => $label,
            ];
        }

        $html = '<div style="display:flex; flex-direction:column; gap:18px;">';

        if ($images !== []) {
            $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap;">';

            foreach ($images as $imageUrl) {
                $escapedUrl = e($imageUrl);

                $html .= '
                    <a href="' . $escapedUrl . '" target="_blank" rel="noopener noreferrer">
                        <img
                            src="' . $escapedUrl . '"
                            alt="Onderhoudsfoto"
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
