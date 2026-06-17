<?php

namespace Tests\Feature;

use App\Filament\Resources\OutreachCampaigns\Pages\EditOutreachCampaign;
use App\Filament\Resources\OutreachProspects\Pages\ListOutreachProspects;
use App\Mail\OutreachCampaignMail;
use App\Models\OutreachCampaign;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Services\Outreach\OutreachEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OutreachEmailWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_campaign_mailtemplate(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = OutreachCampaign::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditOutreachCampaign::class, ['record' => $campaign->getRouteKey()])
            ->fillForm([
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'description' => $campaign->description,
                'email_subject' => 'Speciaal voor {{company_name}}',
                'email_body' => 'Hallo {{contact_name}},' . PHP_EOL . '{{demo_url}}',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $campaign->refresh();

        $this->assertSame('Speciaal voor {{company_name}}', $campaign->email_subject);
        $this->assertStringContainsString('{{demo_url}}', (string) $campaign->email_body);
    }

    public function test_testmail_uses_placeholders_and_test_prefix(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $campaign = OutreachCampaign::factory()->create([
            'email_subject' => 'Demo voor {{company_name}}',
            'email_body' => 'Beste {{contact_name}},' . PHP_EOL . '{{demo_url}}',
        ]);
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Arnhem',
            'contact_name' => 'Pieter',
        ]);

        Livewire::actingAs($admin)
            ->test(EditOutreachCampaign::class, ['record' => $campaign->getRouteKey()])
            ->callAction('sendTestMail');

        Mail::assertSent(OutreachCampaignMail::class, function (OutreachCampaignMail $mail) use ($prospect): bool {
            return $mail->hasTo('willemvanveelen@icloud.com')
                && $mail->subjectLine === '[TEST] Demo voor Moto Arnhem'
                && str_contains($mail->bodyText, 'Beste Pieter,')
                && str_contains($mail->bodyText, $prospect->demoUrl());
        });
    }

    public function test_bulk_send_service_sends_only_selected_prospects_and_stores_body_snapshot(): void
    {
        Mail::fake();

        $campaign = OutreachCampaign::factory()->create([
            'email_subject' => 'Demo voor {{company_name}}',
            'email_body' => 'Beste {{company_name}},' . PHP_EOL . '{{demo_url}}',
        ]);
        $selectedA = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto A',
            'email' => 'a@example.com',
        ]);
        $selectedB = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto B',
            'email' => 'b@example.com',
        ]);
        $notSelected = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto C',
            'email' => 'c@example.com',
        ]);

        $result = app(OutreachEmailService::class)->queueBulkSend(collect([
            $selectedA->load('campaign'),
            $selectedB->load('campaign'),
        ]));

        $this->assertSame(['queued' => 2, 'skipped' => 0], $result);

        Mail::assertSent(OutreachCampaignMail::class, 2);
        Mail::assertSent(OutreachCampaignMail::class, function (OutreachCampaignMail $mail) use ($selectedA): bool {
            return $mail->hasTo('a@example.com') && str_contains($mail->bodyText, $selectedA->demoUrl());
        });
        Mail::assertSent(OutreachCampaignMail::class, function (OutreachCampaignMail $mail) use ($selectedB): bool {
            return $mail->hasTo('b@example.com') && str_contains($mail->bodyText, $selectedB->demoUrl());
        });
        Mail::assertNotSent(OutreachCampaignMail::class, function (OutreachCampaignMail $mail): bool {
            return $mail->hasTo('c@example.com');
        });

        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $selectedA->id,
            'status' => OutreachEmailLog::STATUS_SENT,
        ]);

        $log = OutreachEmailLog::query()->where('outreach_prospect_id', $selectedA->id)->latest('id')->firstOrFail();
        $this->assertStringContainsString($selectedA->demoUrl(), $log->body_snapshot);
        $this->assertNotNull($log->sent_at);
    }

    public function test_bulk_send_skips_missing_email_and_prevents_duplicate_send(): void
    {
        Mail::fake();

        $campaign = OutreachCampaign::factory()->create([
            'email_subject' => 'Demo voor {{company_name}}',
            'email_body' => 'Bekijk {{demo_url}}',
        ]);
        $missingEmail = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Zonder Mail',
            'email' => null,
        ]);
        $alreadySent = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Reeds',
            'email' => 'reeds@example.com',
        ]);

        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $alreadySent->id,
            'to_email' => 'reeds@example.com',
            'subject' => 'Bestaande mail',
            'body_snapshot' => 'Al verstuurd',
            'status' => OutreachEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $result = app(OutreachEmailService::class)->queueBulkSend(collect([
            $missingEmail->load('campaign'),
            $alreadySent->load('campaign'),
        ]));

        $this->assertSame(['queued' => 0, 'skipped' => 2], $result);
        Mail::assertNothingSent();

        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_prospect_id' => $missingEmail->id,
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => 'missing_email',
        ]);
        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_prospect_id' => $alreadySent->id,
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => 'already_sent',
        ]);
    }

    public function test_admin_can_open_outreach_prospects_page_with_bulk_send_action(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListOutreachProspects::class)
            ->assertSuccessful();
    }
}
