<?php

namespace App\Http\Controllers;

use App\Services\PublicGarageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicGarageController extends Controller
{
    public function __construct(
        private readonly PublicGarageService $publicGarageService,
    ) {
    }

    public function show(string $publicSlug): View
    {
        $vehicle = $this->publicGarageService->findPublicVehicleBySlug($publicSlug);

        abort_if(! $vehicle, 404);

        $vehicleName = $this->publicGarageService->publicVehicleName($vehicle);
        $vehicleHeading = $this->publicGarageService->publicVehicleHeading($vehicle);
        $canonicalUrl = $this->publicGarageService->publicUrl($vehicle);
        $isIndexable = $this->publicGarageService->shouldIndex($vehicle);

        return view('garage.show', [
            'canonicalUrl' => $canonicalUrl,
            'displayAttachments' => (bool) $vehicle->share_attachments_publicly,
            'displayCosts' => (bool) $vehicle->share_costs_publicly,
            'isIndexable' => $isIndexable,
            'introText' => $this->publicGarageService->publicIntroText($vehicle),
            'metaDescription' => sprintf(
                'Bekijk de onderhoudshistorie, kilometerstanden, werkzaamheden en documentatie van deze %s in GarageBook.',
                trim($vehicleHeading),
            ),
            'metaRobots' => $isIndexable ? 'index,follow' : 'noindex,follow',
            'metaTitle' => trim($vehicleName) . ' onderhoudshistorie | GarageBook',
            'typeSpecificLandingUrl' => $this->publicGarageService->typeSpecificLandingUrl($vehicle),
            'vehicle' => $vehicle,
            'vehicleHeading' => $vehicleHeading,
            'vehicleName' => $vehicleName,
            'vehiclePhotos' => $this->publicGarageService->publicVehiclePhotos($vehicle),
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
