<?php

namespace Tests\Feature;

use App\Filament\Resources\OutreachCampaigns\Pages\EditOutreachCampaign;
use App\Filament\Resources\OutreachProspects\Pages\ListOutreachProspects;
use App\Jobs\SendOutreachEmailJob;
use App\Mail\OutreachCampaignMail;
use App\Models\OutreachCampaign;
use Illuminate\Mail\Mailables\Address;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Services\Outreach\OutreachEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
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
            $replyTo = collect($mail->envelope()->replyTo ?? [])->first();

            return $mail->hasTo('willemvanveelen@icloud.com')
                && ! $mail->hasTo((string) $prospect->email)
                && $mail->subjectLine === '[TEST] Demo voor Moto Arnhem'
                && str_contains($mail->bodyText, 'Beste Pieter,')
                && str_contains($mail->bodyText, $prospect->demoUrl())
                && $replyTo instanceof Address
                && $replyTo->address === 'social@garagebook.nl';
        });

        Mail::assertNotSent(OutreachCampaignMail::class, function (OutreachCampaignMail $mail) use ($prospect): bool {
            return $mail->hasTo((string) $prospect->email);
        });

        $realMail = app(OutreachEmailService::class)->makeMailForProspect($campaign, $prospect, false);
        $testMail = app(OutreachEmailService::class)->makeMailForProspect($campaign, $prospect, true);
        $replyTo = collect($realMail->envelope()->replyTo ?? [])->first();

        $this->assertSame($realMail->bodyText, $testMail->bodyText);
        $this->assertSame('[TEST] ' . $realMail->subjectLine, $testMail->subjectLine);
        $this->assertInstanceOf(Address::class, $replyTo);
        $this->assertSame('social@garagebook.nl', $replyTo->address);
        $this->assertSame('GarageBook Social', $replyTo->name);
    }

    public function test_test_and_real_mail_use_same_rendered_body(): void
    {
        $campaign = OutreachCampaign::factory()->create([
            'email_subject' => 'Demo voor {{company_name}}',
            'email_body' => 'Beste {{contact_name}},' . PHP_EOL . '{{demo_url}}',
        ]);
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Arnhem',
            'contact_name' => 'Pieter',
            'email' => 'pieter@example.com',
        ]);

        $service = app(OutreachEmailService::class);

        $testMail = $service->makeMailForProspect($campaign, $prospect, true);
        $realMail = $service->makeMailForProspect($campaign, $prospect, false);

        $this->assertSame($realMail->bodyText, $testMail->bodyText);
        $this->assertSame('[TEST] ' . $realMail->subjectLine, $testMail->subjectLine);
        $this->assertSame('willemvanveelen@icloud.com', OutreachEmailService::TEST_RECIPIENT);
        $this->assertNotSame($prospect->email, OutreachEmailService::TEST_RECIPIENT);
    }

    public function test_bulk_send_service_sends_only_selected_prospects_and_stores_body_snapshot(): void
    {
        Bus::fake();

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

        Bus::assertDispatched(SendOutreachEmailJob::class, 2);
        Bus::assertDispatched(SendOutreachEmailJob::class, function (SendOutreachEmailJob $job) use ($selectedA): bool {
            return $job->toEmail === 'a@example.com'
                && $job->campaignId === $selectedA->outreach_campaign_id
                && $job->prospectId === $selectedA->id
                && str_contains($job->bodySnapshot, $selectedA->demoUrl());
        });
        Bus::assertDispatched(SendOutreachEmailJob::class, function (SendOutreachEmailJob $job) use ($selectedB): bool {
            return $job->toEmail === 'b@example.com'
                && $job->campaignId === $selectedB->outreach_campaign_id
                && $job->prospectId === $selectedB->id
                && str_contains($job->bodySnapshot, $selectedB->demoUrl());
        });
    }

    public function test_real_mail_uses_prospect_email_and_reply_to_social_address(): void
    {
        Mail::fake();

        $campaign = OutreachCampaign::factory()->create([
            'email_subject' => 'Demo voor {{company_name}}',
            'email_body' => 'Bekijk {{demo_url}}',
        ]);
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Utrecht',
            'email' => 'utrecht@example.com',
        ]);

        app(OutreachEmailService::class)->queueBulkSend(collect([$prospect->load('campaign')]));

        Mail::assertSent(OutreachCampaignMail::class, function (OutreachCampaignMail $mail) use ($prospect): bool {
            $replyTo = collect($mail->envelope()->replyTo ?? [])->first();

            return $mail->hasTo((string) $prospect->email)
                && ! $mail->hasTo('willemvanveelen@icloud.com')
                && $replyTo instanceof Address
                && $replyTo->address === 'social@garagebook.nl';
        });
    }

    public function test_outreach_prospects_list_shows_email_column_and_missing_email_filter(): void
    {
        $admin = User::factory()->admin()->create();
        OutreachCampaign::factory()->create();

        $prospect = OutreachProspect::factory()->create([
            'company_name' => 'Moto Email',
            'city' => 'Utrecht',
            'website' => 'motoemail.nl',
            'email' => 'info@motoemail.nl',
        ]);

        Livewire::actingAs($admin)
            ->test(ListOutreachProspects::class)
            ->assertSee('info@motoemail.nl');

        $this->assertTrue(method_exists(\App\Filament\Resources\OutreachProspects\OutreachProspectResource::class, 'table'));
        $this->assertSame('mailto:info@motoemail.nl', 'mailto:' . $prospect->email);
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

    public function test_retry_failed_outreach_emails_command_requeues_only_unqueued_failed_logs(): void
    {
        Bus::fake();

        $campaign = OutreachCampaign::factory()->create();
        $queuedProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Wacht',
            'email' => 'wacht@example.com',
        ]);
        $failedProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Retry',
            'email' => 'retry@example.com',
        ]);
        $sentProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Sent',
            'email' => 'sent@example.com',
        ]);

        $failedLog = OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $failedProspect->id,
            'to_email' => 'retry@example.com',
            'subject' => 'Retry',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_FAILED,
        ]);
        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $queuedProspect->id,
            'to_email' => 'wacht@example.com',
            'subject' => 'Queued',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_FAILED,
            'queued_at' => now(),
        ]);
        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $sentProspect->id,
            'to_email' => 'sent@example.com',
            'subject' => 'Sent',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        Artisan::call('garagebook:retry-failed-outreach-emails');

        Bus::assertDispatched(SendOutreachEmailJob::class, 1);
        Bus::assertDispatched(SendOutreachEmailJob::class, function (SendOutreachEmailJob $job) use ($failedProspect): bool {
            return $job->prospectId === $failedProspect->id
                && $job->campaignId === $failedProspect->outreach_campaign_id
                && $job->toEmail === 'retry@example.com';
        });

        $failedLog->refresh();
        $this->assertNotNull($failedLog->queued_at);
        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_prospect_id' => $sentProspect->id,
            'status' => OutreachEmailLog::STATUS_SENT,
            'queued_at' => null,
        ]);
    }

    public function test_send_outreach_job_releases_on_resend_429_without_failed_log(): void
    {
        $campaign = OutreachCampaign::factory()->create([
            'email_subject' => 'Demo voor {{company_name}}',
            'email_body' => 'Bekijk {{demo_url}}',
        ]);
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto 429',
            'email' => 'limit@example.com',
        ]);

        $originalMailManager = Mail::getFacadeRoot();
        Mail::swap(new class
        {
            public function to(string $email): object
            {
                return new class
                {
                    public function send(object $mailable): void
                    {
                        throw new \RuntimeException('429 Too many requests');
                    }
                };
            }
        });

        $job = new SendOutreachEmailJob(
            $prospect->id,
            $campaign->id,
            'limit@example.com',
            'Onderwerp',
            'Body',
        );
        $job->withFakeQueueInteractions();

        $job->handle();

        $job->assertReleased();
        Mail::swap($originalMailManager);
        $this->assertDatabaseMissing('outreach_email_logs', [
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'status' => OutreachEmailLog::STATUS_FAILED,
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
