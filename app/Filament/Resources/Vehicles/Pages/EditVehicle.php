<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Services\DistanceUnitService;
use App\Support\MediaPath;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $service = app(DistanceUnitService::class);
        $unit = $service->normalizeUnit($data['distance_unit'] ?? $this->record->distance_unit);

        $data['distance_unit'] = $unit;
        $data['current_km'] = $service->fromKilometers($data['current_km'] ?? null, $unit, 0);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $service = app(DistanceUnitService::class);
        $data['distance_unit'] = $service->normalizeUnit($data['distance_unit'] ?? null);
        $data['current_km'] = (int) round(
            $service->toKilometers($data['current_km'] ?? null, $data['distance_unit'], 0) ?? 0
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getHeading(): string | HtmlString
    {
        $vehicle = $this->record;

        $photos = [];

        if ($vehicle->photo) {
            $photos[] = Storage::url($vehicle->photo);
        }

        if (!empty($vehicle->photos)) {
            foreach ($vehicle->photos as $photo) {
                $photos[] = Storage::url($photo);
            }
        }

        $files = [];

        if (! empty($vehicle->media_attachments)) {
            foreach ($vehicle->media_attachments as $attachment) {
                $files[] = [
                    'label' => MediaPath::label($attachment),
                    'url' => Storage::url($attachment),
                    'type' => MediaPath::isVideo($attachment) ? 'Video' : (MediaPath::isPdf($attachment) ? 'PDF' : 'Bestand'),
                ];
            }
        }

        $photosJson = json_encode($photos);
        $filesJson = json_encode($files);

        $html = '
        <div style="display:flex; flex-direction:column; gap:15px;">
            <div style="display:flex; gap:10px; flex-wrap:wrap;">';

        foreach ($photos as $index => $photoUrl) {
            $size = $index === 0 ? '300px' : '100px';

            $html .= '
                <img
                    src="' . $photoUrl . '"
                    onclick="openGallery(' . $index . ')"
                    style="width:' . $size . '; height:' . ($index === 0 ? 'auto' : '100px') . '; object-fit:cover; border-radius:12px; cursor:pointer;"
                >
            ';
        }

        $html .= '
            </div>
            <div id="vehicleFiles" style="display:flex; flex-direction:column; gap:8px;">
            </div>
            <div>Voertuig bewerken</div>
        </div>

        <div id="galleryOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center;">
            <button onclick="prevPhoto()" style="position:absolute; left:30px; font-size:40px; color:white;">‹</button>
            <img id="galleryImage" style="max-width:90vw; max-height:90vh; border-radius:12px;">
            <button onclick="nextPhoto()" style="position:absolute; right:30px; font-size:40px; color:white;">›</button>
            <button onclick="closeGallery()" style="position:absolute; top:30px; right:30px; font-size:30px; color:white;">✕</button>
        </div>

        <script>
            const photos = ' . $photosJson . ';
            const files = ' . $filesJson . ';
            let currentPhoto = 0;

            const fileContainer = document.getElementById("vehicleFiles");

            if (files.length) {
                const heading = document.createElement("div");
                heading.style.fontWeight = "700";
                heading.textContent = "Overige bestanden";
                fileContainer.appendChild(heading);

                files.forEach((file) => {
                    const link = document.createElement("a");
                    link.href = file.url;
                    link.target = "_blank";
                    link.rel = "noopener noreferrer";
                    link.textContent = `${file.type}: ${file.label}`;
                    link.style.color = "#111827";
                    link.style.textDecoration = "underline";
                    fileContainer.appendChild(link);
                });
            }

            function openGallery(index) {
                currentPhoto = index;
                document.getElementById("galleryImage").src = photos[currentPhoto];
                document.getElementById("galleryOverlay").style.display = "flex";
            }

            function closeGallery() {
                document.getElementById("galleryOverlay").style.display = "none";
            }

            function nextPhoto() {
                currentPhoto = (currentPhoto + 1) % photos.length;
                document.getElementById("galleryImage").src = photos[currentPhoto];
            }

            function prevPhoto() {
                currentPhoto = (currentPhoto - 1 + photos.length) % photos.length;
                document.getElementById("galleryImage").src = photos[currentPhoto];
            }

            document.addEventListener("keydown", function(event) {
                const overlay = document.getElementById("galleryOverlay");

                if (overlay.style.display !== "flex") {
                    return;
                }

                if (event.key === "ArrowRight") {
                    nextPhoto();
                }

                if (event.key === "ArrowLeft") {
                    prevPhoto();
                }

                if (event.key === "Escape") {
                    closeGallery();
                }
            });
        </script>
        ';

        return new HtmlString($html);
    }
}
