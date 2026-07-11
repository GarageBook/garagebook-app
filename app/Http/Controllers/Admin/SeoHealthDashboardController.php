<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoHealthService;
use Illuminate\View\View;

class SeoHealthDashboardController extends Controller
{
    public function __invoke(SeoHealthService $seoHealthService): View
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        return view('admin.seo-health-dashboard', [
            'report' => $seoHealthService->report(),
        ]);
    }
}
