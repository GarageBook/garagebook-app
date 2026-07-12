<?php

use App\Filament\Auth\Register;
use App\Http\Controllers\Admin\SearchConsoleInsightsExportController;
use App\Http\Controllers\Admin\SeoHealthDashboardController;
use App\Http\Controllers\Admin\SeoHealthDashboardExportController;
use App\Http\Controllers\Admin\SeoOpportunitiesExportController;
use App\Http\Controllers\Lifecycle\LifecycleEmailClickController;
use App\Http\Controllers\Lifecycle\LifecycleEmailUnsubscribeController;
use App\Http\Controllers\OutreachDemoIntroDismissController;
use App\Http\Controllers\OutreachDemoLoginController;
use App\Http\Controllers\Public\StartRedirectController;
use App\Http\Controllers\PublicGarageController;
use App\Http\Controllers\PublicImageController;
use App\Http\Controllers\TripPhotoController;
use App\Http\Controllers\VehicleAuthorityController;
use App\Http\Controllers\VehicleDocumentController;
use App\Models\Blog;
use App\Models\MaintenanceLog;
use App\Models\Page;
use App\Models\Vehicle;
use App\Support\InternalContentLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Http\Middleware\SetUpPanel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/admin');
    }

    return view('welcome');
});

Route::get('/start', StartRedirectController::class);

Route::get('/register', Register::class)->name('register');

Route::get('/demo/garage/{token}', OutreachDemoLoginController::class)
    ->name('outreach.demo.login');

Route::get('/lifecycle-emails/click/{user}/{emailKey}', LifecycleEmailClickController::class)
    ->middleware('signed')
    ->name('lifecycle-emails.click');

Route::get('/lifecycle-emails/unsubscribe/{user}', LifecycleEmailUnsubscribeController::class)
    ->middleware('signed')
    ->name('lifecycle-emails.unsubscribe');

Route::get('/website', function () {
    return redirect('/', 301);
})->name('website');

Route::middleware([
    'auth',
    SetUpPanel::class.':admin',
])->get('/admin/seo-health-dashboard', SeoHealthDashboardController::class)
    ->name('admin.seo-health-dashboard');

Route::get('/garage/{publicSlug}', [PublicGarageController::class, 'show'])
    ->name('public-garage.show');

Route::get('/share/{username}/{vehicleSlug}', [PublicGarageController::class, 'legacyRedirect'])
    ->name('public-garage.legacy');

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

    if (auth()->check() && auth()->user()?->first_booklet_downloaded_at === null) {
        auth()->user()->forceFill([
            'first_booklet_downloaded_at' => now(),
        ])->save();
    }

    $pdf = Pdf::loadView('maintenance-share', compact('logs', 'vehicle'));

    return $pdf->download(
        Str::slug($vehicle->nickname ?: $vehicle->brand.' '.$vehicle->model)
        .'-onderhoud.pdf'
    );
});

Route::get('/admin/seo-health-export', SeoHealthDashboardExportController::class)
    ->name('admin.seo-health-dashboard.export');

Route::get('/admin/search-console-insights/export', SearchConsoleInsightsExportController::class)
    ->name('admin.search-console-insights.export');

Route::get('/admin/search-console-insights/opportunities/export', SeoOpportunitiesExportController::class)
    ->name('admin.seo-opportunities.export');

Route::middleware('auth')->group(function () {

    Route::get('/documents/{document}', [VehicleDocumentController::class, 'show'])
        ->name('vehicle-documents.show');

    Route::get('/documents/{document}/download', [VehicleDocumentController::class, 'download'])
        ->name('vehicle-documents.download');

    Route::get('/trips/{trip}/photos/{photoIndex}', [TripPhotoController::class, 'show'])
        ->whereNumber('photoIndex')
        ->name('trip-photos.show');

    Route::post('/demo/garage/intro-dismiss', OutreachDemoIntroDismissController::class)
        ->name('outreach.demo.intro-dismiss');
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

Route::get('/sitemap-garages.xml', [PublicGarageController::class, 'sitemap'])
    ->name('sitemap.garages');

Route::get('/sitemap-onderhoud.xml', [VehicleAuthorityController::class, 'sitemap'])
    ->name('sitemap.onderhoud');

Route::get('/sitemap-vehicle-authority.xml', [VehicleAuthorityController::class, 'authorityIndexSitemap'])
    ->name('sitemap.vehicle-authority');

/*
|--------------------------------------------------------------------------
| VEHICLE AUTHORITY PAGES
|--------------------------------------------------------------------------
*/

Route::get('/onderhoud/{slug}', [VehicleAuthorityController::class, 'show'])
    ->name('onderhoud.show')
    ->where('slug', '[a-z0-9][a-z0-9-]+');

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
