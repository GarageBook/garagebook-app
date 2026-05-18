<?php

namespace App\Http\Controllers;

use App\Models\TripLog;
use App\Support\MediaPath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TripPhotoController extends Controller
{
    public function show(TripLog $trip, int $photoIndex): BinaryFileResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless($trip->vehicle()->where('user_id', auth()->id())->exists(), 404);

        $photos = collect($trip->photos)
            ->filter(fn (mixed $path) => is_string($path) && filled($path) && MediaPath::isImage($path))
            ->values();

        $path = $photos->get($photoIndex);

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        $response = response()->file(
            Storage::disk('local')->path($path),
            [
                'Content-Type' => MediaPath::mimeType($path) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes(basename($path)) . '"',
            ]
        );

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
