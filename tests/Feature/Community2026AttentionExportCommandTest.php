<?php

namespace Tests\Feature;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Community2026AttentionExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_writes_attention_csv_with_suggestions(): void
    {
        $campaign = GrowthCampaign::query()->create([
            'name' => 'Community2026',
            'slug' => 'community2026',
            'description' => 'Community test',
            'status' => GrowthCampaign::STATUS_DRAFT,
        ]);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Invalid Club',
            'website' => 'https://club.example',
            'normalized_domain' => 'club.example',
            'email' => 'bad-address',
            'normalized_email' => 'bad-address',
            'email_status' => GrowthProspect::EMAIL_STATUS_INVALID,
            'verification_required' => true,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_MANUAL_REVIEW,
            'status' => GrowthProspect::LIFECYCLE_MANUAL_REVIEW,
            'skip_reason' => 'invalid_email',
            'notes' => 'Review me',
        ]);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Missing Club',
            'website' => 'https://missing.example',
            'normalized_domain' => 'missing.example',
            'email' => null,
            'normalized_email' => null,
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
            'verification_required' => true,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_ENRICHED,
            'status' => GrowthProspect::LIFECYCLE_ENRICHED,
            'skip_reason' => 'missing_email',
        ]);

        Http::fake([
            'https://club.example' => Http::response($this->invalidClubHtml(), 200),
            'https://club.example' => Http::response($this->contactHtml('info@club.example'), 200),
            'https://missing.example' => Http::response($this->missingClubHtml(), 200),
            'https://missing.example' => Http::response($this->contactHtml('secretariaat@missing.example'), 200),
            '*' => Http::response('', 404),
        ]);

        $output = storage_path('app/imports/community2026_attention.csv');
        File::delete($output);

        $this->artisan('garagebook:community2026-attention-export', [
            '--output' => 'storage/app/imports/community2026_attention.csv',
            '--limit' => 20,
        ])->assertSuccessful()
            ->expectsOutput('Community2026 attention export voltooid.')
            ->expectsOutput('attention records: 2')
            ->expectsOutput('suggested_email gevonden: 2')
            ->expectsOutput('contact_url gevonden: 2');

        $this->assertFileExists($output);
        $rows = $this->readCsv($output);

        $this->assertSame(['name', 'website', 'current_email', 'email_status', 'lifecycle_status', 'skip_reason', 'suggested_email', 'contact_url', 'notes'], $rows[0]);
        $this->assertSame('Invalid Club', $rows[1][0]);
        $this->assertSame('info@club.example', $rows[1][6]);
        $this->assertSame('https://club.example', $rows[1][7]);
        $this->assertSame('Missing Club', $rows[2][0]);
        $this->assertSame('secretariaat@missing.example', $rows[2][6]);
        $this->assertSame('https://missing.example', $rows[2][7]);
    }

    private function invalidClubHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Club Voorbeeld | Contact</title>
</head>
<body>
    <footer>
        <a href="/contact">Contact</a>
        <a href="mailto:info@club.example">info@club.example</a>
    </footer>
</body>
</html>
HTML;
    }

    private function missingClubHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Missing Club</title>
</head>
<body>
    <a href="/bestuur">Bestuur</a>
    <a href="mailto:secretariaat@missing.example">secretariaat@missing.example</a>
</body>
</html>
HTML;
    }

    private function contactHtml(string $email): string
    {
        return <<<HTML
<!doctype html>
<html>
<body>
    <p><a href="mailto:$email">$email</a></p>
</body>
</html>
HTML;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->fail('Kan CSV niet lezen: '.$path);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
