<?php

namespace App\Events\Lifecycle;

use App\Models\User;
use App\Models\VehicleDocument;

class DocumentUploaded
{
    public function __construct(public readonly VehicleDocument $document) {}

    public function user(): ?User
    {
        return $this->document->vehicle()->first()?->user()->first();
    }
}
