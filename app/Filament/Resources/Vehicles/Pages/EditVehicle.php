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

        $html = '<div style="display:flex; flex-direction:column; gap:15px;">';

        if ($vehicle->photo) {
            $photoUrl = Storage::url($vehicle->photo);

            $html .= '<a href="' . $photoUrl . '" target="_blank">';
            $html .= '<img src="' . $photoUrl . '" style="max-width:300px; border-radius:12px; cursor:pointer;">';
            $html .= '</a>';
        }

        if (!empty($vehicle->photos)) {
            $html .= '<div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap;">';

            foreach ($vehicle->photos as $photo) {
                $photoUrl = Storage::url($photo);

                $html .= '<a href="' . $photoUrl . '" target="_blank">';
                $html .= '<img src="' . $photoUrl . '" style="width:100px; height:100px; object-fit:cover; border-radius:10px; cursor:pointer;">';
                $html .= '</a>';
            }

            $html .= '</div>';
        }

        $html .= '<div>Voertuig bewerken</div>';
        $html .= '</div>';

        return new HtmlString($html);
    }
}