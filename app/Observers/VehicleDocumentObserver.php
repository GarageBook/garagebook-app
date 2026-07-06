<?php

namespace App\Observers;

use App\Events\Lifecycle\DocumentUploaded;
use App\Models\VehicleDocument;

class VehicleDocumentObserver
{
    public function created(VehicleDocument $vehicleDocument): void
    {
        event(new DocumentUploaded($vehicleDocument));
    }
}
