<?php

namespace App\Http\Controllers;

use App\Services\VehicleAuthorityService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VehicleAuthorityController extends Controller
{
    public function __construct(
        private readonly VehicleAuthorityService $vehicleAuthorityService,
    ) {}

    public function show(string $slug): View
    {
        $data = $this->vehicleAuthorityService->resolveBySlug($slug);

        abort_if($data === null, 404);

        return view('onderhoud.show', $data);
    }

    public function sitemap(): Response
    {
        $slugs = $this->vehicleAuthorityService->allModelSlugs();

        return response()
            ->view('sitemap-onderhoud', compact('slugs'))
            ->header('Content-Type', 'application/xml');
    }
}
