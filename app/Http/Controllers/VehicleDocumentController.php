<?php

namespace App\Http\Controllers;

use App\Models\VehicleDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehicleDocumentController extends Controller
{
    public function show(VehicleDocument $document): BinaryFileResponse
    {
        $this->authorizeDocument($document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return response()->file(
            Storage::disk('local')->path($document->file_path),
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes($document->original_filename ?: basename($document->file_path)) . '"',
            ]
        );
    }

    public function download(VehicleDocument $document): BinaryFileResponse
    {
        $this->authorizeDocument($document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $document->original_filename ?: basename($document->file_path)
        );
    }

    private function authorizeDocument(VehicleDocument $document): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless($document->vehicle()->where('user_id', auth()->id())->exists(), 404);
    }
}
