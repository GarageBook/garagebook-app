<?php

namespace Tests\Feature;

use App\Services\Growth\Discovery\WebsiteDiscoveryProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteDiscoverySubtypeTest extends TestCase
{
    public static function campaignSubtypes(): array
    {
        return [
            ['community', 'Oldtimer vereniging', 'oldtimer_club'],
            ['community', 'Camperclub', 'camper_club'],
            ['community', 'Youngtimer club', 'youngtimer_club'],
            ['partner', 'Oldtimer restauratie', 'oldtimer_restoration'],
            ['partner', 'Camper specialist', 'camper_specialist'],
            ['partner', 'Youngtimer restauratie', 'youngtimer_restoration'],
        ];
    }

    #[DataProvider('campaignSubtypes')]
    public function test_website_subtype_respects_the_prospect_type(string $type, string $title, string $expected): void
    {
        Http::fake(['*' => Http::response('<html><head><title>'.$title.'</title></head><body>Contact: info@example.org</body></html>')]);

        $results = (new WebsiteDiscoveryProvider(['https://example.org'], defaultProspectType: $type))->discover();

        $this->assertCount(1, $results);
        $this->assertSame($expected, $results[0]->prospectSubtype);
        $this->assertSame($type, $results[0]->prospectType);
    }

    public function test_explicit_provider_subtype_takes_precedence(): void
    {
        Http::fake(['*' => Http::response('<html><head><title>Oldtimer vereniging</title></head></html>')]);
        $results = (new WebsiteDiscoveryProvider(
            ['https://example.org'], defaultProspectType: 'community', defaultProspectSubtype: 'brand_club',
        ))->discover();

        $this->assertSame('brand_club', $results[0]->prospectSubtype);
    }

    public function test_partner_command_supplies_partner_context_for_explicit_urls(): void
    {
        Http::fake(['*' => Http::response('<html><head><title>Oldtimer restauratie</title></head><body>info@example.org</body></html>')]);
        $output = tempnam(sys_get_temp_dir(), 'partner-discovery-');
        $rejected = tempnam(sys_get_temp_dir(), 'partner-rejected-');

        try {
            $this->artisan('garagebook:discover-partner2026', [
                '--urls' => 'https://example.org', '--output' => $output, '--rejected' => $rejected,
            ])->assertSuccessful();

            $rows = array_map('str_getcsv', file($output, FILE_IGNORE_NEW_LINES));
            $this->assertCount(2, $rows);
            $this->assertSame('partner', $rows[1][8]);
            $this->assertSame('oldtimer_restoration', $rows[1][9]);
        } finally {
            unlink($output);
            unlink($rejected);
        }
    }
}
