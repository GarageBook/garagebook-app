<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

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

        $photosJson = json_encode($photos);

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
            let currentPhoto = 0;

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