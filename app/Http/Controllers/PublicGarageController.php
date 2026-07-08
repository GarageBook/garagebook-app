<?php

namespace App\Http\Controllers;

use App\Services\PublicGarageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicGarageController extends Controller
{
    public function __construct(
        private readonly PublicGarageService $publicGarageService,
    ) {}

    public function show(Request $request, string $publicSlug): View|RedirectResponse
    {
        $vehicle = $this->publicGarageService->findPublicVehicleBySlug($publicSlug);

        abort_if(! $vehicle, 404);

        $vehicleName = $this->publicGarageService->publicVehicleName($vehicle);
        $vehicleHeading = $this->publicGarageService->publicVehicleHeading($vehicle);
        $canonicalUrl = $this->publicGarageService->publicUrl($vehicle);
        if ($request->getQueryString() !== null) {
            return redirect()->to($canonicalUrl, 301);
        }
        $isIndexable = $this->publicGarageService->shouldIndex($vehicle);
        $publicStats = $this->publicGarageService->publicStats($vehicle);
        $timelineItems = $this->publicGarageService->publicTimelineItems($vehicle);

        return view('garage.show', [
            'canonicalUrl' => $canonicalUrl,
            'historyHighlights' => $this->publicGarageService->publicHistoryHighlights($vehicle),
            'introText' => $this->publicGarageService->publicIntroText($vehicle),
            'isIndexable' => $isIndexable,
            'maintenanceSeoContent' => $this->publicGarageService->publicMaintenanceSeoContent($vehicle),
            'metaDescription' => $this->publicGarageService->publicMetaDescription($vehicle),
            'metaRobots' => $isIndexable ? 'index,follow' : 'noindex,follow',
            'metaTitle' => trim($vehicleName).' voertuiggeschiedenis | GarageBook',
            'publicStats' => $publicStats,
            'shareCues' => $this->publicGarageService->publicShareCues($vehicle),
            'timelineItems' => $timelineItems,
            'typeSpecificLandingUrl' => $this->publicGarageService->typeSpecificLandingUrl($vehicle),
            'vehicle' => $vehicle,
            'vehicleHeading' => $vehicleHeading,
            'vehicleName' => $vehicleName,
            'vehiclePhotos' => $this->publicGarageService->publicVehiclePhotos($vehicle),
            'verificationNote' => $this->publicGarageService->publicVerificationNote($vehicle),
        ]);
    }

    public function legacyRedirect(string $username, string $vehicleSlug): RedirectResponse
    {
        $vehicle = $this->publicGarageService->findLegacyPublicVehicle($username, $vehicleSlug);

        abort_if(! $vehicle, 404);

        return redirect()->to($this->publicGarageService->publicUrl($vehicle), 301);
    }

    public function sitemap(): Response
    {
        return response()
            ->view('sitemap-garages', [
                'vehicles' => $this->publicGarageService->indexableVehicles(),
            ])
            ->header('Content-Type', 'application/xml');
    }
}
