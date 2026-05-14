<?php

use App\Models\MaintenanceLog;
use App\Models\Page;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Blog;
use App\Http\Controllers\PublicImageController;
use App\Http\Controllers\VehicleDocumentController;
use App\Support\InternalContentLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/admin');
    }

    return view('welcome');
});

Route::get('/start', function () {
    $queryString = request()->server('QUERY_STRING');
    $targetUrl = '/admin/register';

    if ($queryString) {
        $targetUrl .= '?'.$queryString;
    }

    return redirect($targetUrl, 302);
});

Route::get('/website', function () {
    return redirect('/', 301);
})->name('website');

Route::get('/share/{username}/{vehicleSlug}', function ($username, $vehicleSlug) {
    $users = User::all();

    $user = $users->first(function ($user) use ($username) {
        return Str::slug(trim($user->name)) === trim($username);
    });

    if (! $user) {
        abort(404, 'User not found');
    }

    $vehicles = Vehicle::where('user_id', $user->id)->get();

    $vehicle = $vehicles->first(function ($vehicle) use ($vehicleSlug) {
        $slug = Str::slug(
            trim($vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model)
        );

        return $slug === trim($vehicleSlug);
    });

    if (! $vehicle) {
        abort(404, 'Vehicle not found');
    }

    $logs = MaintenanceLog::where('vehicle_id', $vehicle->id)
        ->latest('maintenance_date')
        ->get();

    return view('maintenance-share', compact('logs', 'vehicle', 'user'));
});

Route::get('/maintenance/pdf', function () {
    $vehicles = Vehicle::query()
        ->where('user_id', auth()->id())
        ->latest()
        ->get();

    $requestedVehicleId = request()->integer('vehicle_id');

    $vehicle = $requestedVehicleId
        ? $vehicles->firstWhere('id', $requestedVehicleId)
        : $vehicles->first();

    abort_if(! $vehicle, 404);

    $logs = MaintenanceLog::where('vehicle_id', $vehicle->id)
        ->latest('maintenance_date')
        ->get();

    $pdf = Pdf::loadView('maintenance-share', compact('logs', 'vehicle'));

    return $pdf->download(
        Str::slug($vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model)
        . '-onderhoud.pdf'
    );
});

Route::middleware('auth')->group(function () {
    Route::get('/documents/{document}', [VehicleDocumentController::class, 'show'])
        ->name('vehicle-documents.show');

    Route::get('/documents/{document}/download', [VehicleDocumentController::class, 'download'])
        ->name('vehicle-documents.download');
});

/*
|--------------------------------------------------------------------------
| BLOGS
|--------------------------------------------------------------------------
*/

Route::get('/blogs', function () {
    $blogs = Blog::whereNotNull('published_at')
        ->latest('published_at')
        ->get();

    return view('blogs.index', compact('blogs'));
});

Route::get('/blogs/{slug}', function ($slug) {
    $blog = Blog::where('slug', $slug)
        ->whereNotNull('published_at')
        ->firstOrFail();

    return view('blogs.show', compact('blog'));
});

/*
|--------------------------------------------------------------------------
| BLOG IMAGES (FIXED)
|--------------------------------------------------------------------------
*/

Route::get('/blog-image/{path}', [PublicImageController::class, 'blog'])
    ->where('path', '.*');

Route::get('/sitemap.xml', function () {
    $pages = Page::query()
        ->where('indexable', true)
        ->whereNotIn('slug', InternalContentLinks::SITEMAP_EXCLUDED_PAGE_SLUGS)
        ->orderBy('slug')
        ->get();

    return response()
        ->view('sitemap', compact('pages'))
        ->header('Content-Type', 'application/xml');
});

Route::get('/robots.txt', function () {
    return response()
        ->view('robots')
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::get('/{slug}', function ($slug) {
    $page = Page::where('slug', $slug)->first();

    if ($page) {
        return view('pages.show', compact('page'));
    }

    abort(404);
});
