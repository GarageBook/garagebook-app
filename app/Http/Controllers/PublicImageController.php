<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicImageController extends Controller
{
    public function blog(Request $request, string $path): BinaryFileResponse
    {
        $storageRoot = storage_path('app/public');
        $fullPath = $storageRoot . DIRECTORY_SEPARATOR . $path;

        abort_unless(File::exists($fullPath), 404);

        $realRoot = realpath($storageRoot);
        $realPath = realpath($fullPath);

        abort_if($realRoot === false || $realPath === false, 404);
        abort_unless(str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR), 404);

        if ($optimized = $this->webpVariant($request, $realPath)) {
            return $this->fileResponse($optimized, 'image/webp', true);
        }

        $mimeType = File::mimeType($realPath) ?: 'application/octet-stream';

        return $this->fileResponse($realPath, $mimeType, false);
    }

    private function webpVariant(Request $request, string $realPath): ?string
    {
        $acceptHeader = (string) $request->header('Accept');
        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

        if (! str_contains($acceptHeader, 'image/webp') || ! in_array($extension, ['jpg', 'jpeg', 'png'], true) || ! function_exists('imagewebp')) {
            return null;
        }

        $optimizedDirectory = storage_path('app/public/optimized/blog-images');
        File::ensureDirectoryExists($optimizedDirectory);

        $hash = sha1($realPath . '|' . filemtime($realPath) . '|' . filesize($realPath));
        $optimizedPath = $optimizedDirectory . DIRECTORY_SEPARATOR . $hash . '.webp';

        if (File::exists($optimizedPath)) {
            return $optimizedPath;
        }

        $image = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($realPath),
            'png' => @imagecreatefrompng($realPath),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $saved = imagewebp($image, $optimizedPath, 82);
        imagedestroy($image);

        return $saved ? $optimizedPath : null;
    }

    private function fileResponse(string $path, string $mimeType, bool $varyAccept): BinaryFileResponse
    {
        $response = response()->file($path, [
            'Content-Type' => $mimeType,
        ]);

        $maxAge = 604800;
        $response->setPublic();
        $response->setMaxAge($maxAge);
        $response->setSharedMaxAge($maxAge);
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}, s-maxage={$maxAge}, stale-while-revalidate=86400");
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');

        if ($varyAccept) {
            $response->headers->set('Vary', 'Accept');
        }

        return $response;
    }
}
