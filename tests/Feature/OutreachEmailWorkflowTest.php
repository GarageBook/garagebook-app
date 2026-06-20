<?php

namespace Tests\Feature;

use App\Filament\Resources\OutreachCampaigns\Pages\EditOutreachCampaign;
use App\Filament\Resources\OutreachProspects\Pages\ListOutreachProspects;
use App\Jobs\SendOutreachEmailJob;
use App\Mail\OutreachCampaignMail;
use App\Models\OutreachCampaign;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Services\Outreach\OutreachEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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

    public function test_outreach_prospects_list_shows_sent_when_sent_log_has_later_already_sent_skip(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = OutreachCampaign::factory()->create();
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Sent Later Skip',
            'email' => 'sent-later-skip@example.com',
        ]);

        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => 'sent-later-skip@example.com',
            'subject' => 'Sent',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_SENT,
            'sent_at' => now()->subMinute(),
        ]);
        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => 'sent-later-skip@example.com',
            'subject' => 'Skipped',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => 'already_sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListOutreachProspects::class)
            ->assertSee('Moto Sent Later Skip')
            ->assertSee('verstuurd')
            ->assertDontSee('overgeslagen');
    }

    public function test_outreach_prospects_list_keeps_skipped_when_no_sent_log_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = OutreachCampaign::factory()->create();
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Only Skipped',
            'email' => 'only-skipped@example.com',
        ]);

        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => 'only-skipped@example.com',
            'subject' => 'Skipped',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => 'missing_email',
        ]);

        Livewire::actingAs($admin)
            ->test(ListOutreachProspects::class)
            ->assertSee('Moto Only Skipped')
            ->assertSee('overgeslagen');
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

    public function test_retry_overlooked_outreach_emails_command_queues_unsent_prospects_and_skips_sent_prospects(): void
    {
        Bus::fake();

        $campaign = OutreachCampaign::factory()->create();

        $unsentProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Nieuw',
            'email' => 'nieuw@example.com',
        ]);

        $failedProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Opnieuw',
            'email' => 'opnieuw@example.com',
        ]);

        $skippedProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Skipped',
            'email' => 'skipped@example.com',
        ]);

        $sentProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Sent',
            'email' => 'sent@example.com',
        ]);

        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $failedProspect->id,
            'to_email' => 'opnieuw@example.com',
            'subject' => 'Failed',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_FAILED,
            'queued_at' => now(),
        ]);

        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $skippedProspect->id,
            'to_email' => 'skipped@example.com',
            'subject' => 'Skipped',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_SKIPPED,
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

        Artisan::call('garagebook:retry-overlooked-outreach-emails', [
            '--campaign' => (string) $campaign->id,
        ]);

        Bus::assertDispatched(SendOutreachEmailJob::class, 3);
        Bus::assertDispatched(SendOutreachEmailJob::class, function (SendOutreachEmailJob $job) use ($unsentProspect): bool {
            return $job->prospectId === $unsentProspect->id
                && $job->campaignId === $unsentProspect->outreach_campaign_id
                && $job->toEmail === 'nieuw@example.com';
        });
        Bus::assertDispatched(SendOutreachEmailJob::class, function (SendOutreachEmailJob $job) use ($failedProspect): bool {
            return $job->prospectId === $failedProspect->id
                && $job->campaignId === $failedProspect->outreach_campaign_id
                && $job->toEmail === 'opnieuw@example.com';
        });
        Bus::assertDispatched(SendOutreachEmailJob::class, function (SendOutreachEmailJob $job) use ($skippedProspect): bool {
            return $job->prospectId === $skippedProspect->id
                && $job->campaignId === $skippedProspect->outreach_campaign_id
                && $job->toEmail === 'skipped@example.com';
        });
        Bus::assertNotDispatched(SendOutreachEmailJob::class, function (SendOutreachEmailJob $job) use ($sentProspect): bool {
            return $job->prospectId === $sentProspect->id;
        });

        $failedProspect->refresh();
        $skippedProspect->refresh();

        $this->assertNotNull($failedProspect->emailLogs()->latest('id')->first()?->queued_at);
        $this->assertNotNull($skippedProspect->emailLogs()->latest('id')->first()?->queued_at);

        $output = Artisan::output();

        $this->assertStringContainsString('Gevonden prospects zonder sent-log: 3', $output);
        $this->assertStringContainsString("Gequeue'd: 3", $output);
        $this->assertStringNotContainsString('al succesvol verstuurd', $output);
    }

    public function test_retry_overlooked_outreach_emails_command_skips_invalid_and_missing_email_with_reason(): void
    {
        Bus::fake();

        $campaign = OutreachCampaign::factory()->create();

        $missingEmailProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Zonder Mail',
            'email' => null,
        ]);

        $invalidEmailProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Ongeldig',
            'email' => 'ongeldig@',
        ]);

        Artisan::call('garagebook:retry-overlooked-outreach-emails', [
            '--campaign' => (string) $campaign->id,
        ]);

        Bus::assertNothingDispatched();

        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_prospect_id' => $missingEmailProspect->id,
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => 'missing_email',
        ]);
        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_prospect_id' => $invalidEmailProspect->id,
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => 'invalid_email',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('leeg e-mailadres', $output);
        $this->assertStringContainsString('ongeldig e-mailadres', $output);
    }

    public function test_send_outreach_job_logs_failure_with_safe_subject_and_body_snapshot(): void
    {
        $campaign = OutreachCampaign::factory()->create();
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'email' => 'fail-safe@example.com',
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
                        throw new \RuntimeException('Resend rejected message');
                    }
                };
            }
        });

        try {
            $job = new SendOutreachEmailJob(
                $prospect->id,
                $campaign->id,
                'fail-safe@example.com',
                null,
                null,
            );

            try {
                $job->handle();
                $this->fail('Expected outreach send failure.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Resend rejected message', $exception->getMessage());
            }
        } finally {
            Mail::swap($originalMailManager);
        }

        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => 'fail-safe@example.com',
            'subject' => 'Outreach mail failed',
            'body_snapshot' => '',
            'status' => OutreachEmailLog::STATUS_FAILED,
            'error' => 'Resend rejected message',
        ]);
    }

    public function test_send_outreach_job_skips_when_sent_log_already_exists(): void
    {
        Mail::fake();

        $campaign = OutreachCampaign::factory()->create();
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'email' => 'already-job@example.com',
        ]);

        OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => 'already-job@example.com',
            'subject' => 'Already sent',
            'body_snapshot' => 'Body',
            'status' => OutreachEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $job = new SendOutreachEmailJob(
            $prospect->id,
            $campaign->id,
            'already-job@example.com',
            'New subject',
            'New body',
        );

        $job->handle();

        Mail::assertNothingSent();
        $this->assertSame(1, OutreachEmailLog::query()
            ->where('outreach_campaign_id', $campaign->id)
            ->where('outreach_prospect_id', $prospect->id)
            ->where('status', OutreachEmailLog::STATUS_SENT)
            ->count());
        $this->assertDatabaseHas('outreach_email_logs', [
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => 'already-job@example.com',
            'subject' => 'New subject',
            'body_snapshot' => 'New body',
            'status' => OutreachEmailLog::STATUS_SKIPPED,
            'error' => 'already_sent',
        ]);
    }

    public function test_outreach_job_rate_limiter_releases_fifth_job_without_running_handler(): void
    {
        RateLimiter::clear(md5('outreach-email' . 'resend-outreach-email'));

        $middleware = (new RateLimited('outreach-email'))->releaseAfter(1);
        $job = new class
        {
            public array $released = [];

            public function release(int $delay): void
            {
                $this->released[] = $delay;
            }
        };
        $handled = 0;

        for ($i = 0; $i < 5; $i++) {
            $middleware->handle($job, function () use (&$handled): void {
                $handled++;
            });
        }

        $this->assertSame(4, $handled);
        $this->assertSame([1], $job->released);
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
