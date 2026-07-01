<?php

namespace App\Services\Growth\Partner2026;

class PartsDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'parts_webshop';
    }

    public function urls(): array
    {
        return $this->urlsForDomains(['winparts.nl', 'mijnautoonderdelen.nl', 'autoonderdelen.nl', 'autoaccessoires.nl', 'motoraccessoires.nl', 'motorkledingcenter.com', 'motorparts.nl', 'bikeroutfit.nl', 'motorhuis.nl', 'autoshop.nl', 'carparts.nl', 'onderdelenlijn.nl']);
    }
}
