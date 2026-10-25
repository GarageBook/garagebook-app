<?php

namespace App\Console\Commands;

use App\Mail\PhpSecurityServiceMail;
use App\Models\User;
use App\Support\ServiceMailLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendPhpSecurityServiceMailCommand extends Command
{
    protected $signature = 'garagebook:php-security-service-mail
        {--dry-run : Alleen tellen; dit is standaard}
        {--send : Definitief verzenden, uitsluitend na expliciete goedkeuring}
        {--test-to= : Verstuur uitsluitend naar dit expliciete testadres}
        {--limit= : Deelverzending: maximaal dit aantal, begrensd door beschikbaar providerquotum}
        {--expect= : Vereist totaal aantal unieke ontvangers voor bulkverzending}';

    protected $description = 'Eenmalige PHP-service-e-mail; standaard dry-run, hervat zonder automatische herverzending.';

    public function handle(): int
    {
        // A local process lock covers selection, durable claims and pacing across runs.
        $lock = null;
        $oldMask = umask(0077);
        try {
            $dir = storage_path('app/private/service-mail-php-security-2026-10-21');
            if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                throw new RuntimeException('Private opslag niet beschikbaar.');
            }
            $lock = fopen($dir.'/run.lock', 'c');
            if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Er draait al een uitvoering.');
            }
            $mail = new PhpSecurityServiceMail;
            $html = $mail->render();
            $fingerprint = hash('sha256', PhpSecurityServiceMail::SUBJECT.PhpSecurityServiceMail::BODY.$html.json_encode(config('mail.from')));
            $ledger = new ServiceMailLedger($dir.'/deliveries.sqlite', $fingerprint);
            $recipients = ServiceMailLedger::recipients(User::query()->pluck('email'));
            $total = count($recipients);
            $limit = $this->option('limit');
            if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
                throw new RuntimeException('Ongeldige verzendlimiet.');
            }
            $testTo = $this->option('test-to');
            if ($testTo !== null) {
                $recipients = ServiceMailLedger::recipients([$testTo]);
                if (count($recipients) !== 1) {
                    throw new RuntimeException('Ongeldig testadres.');
                }
            }
            $counts = ['pending' => 0, 'accepted' => 0, 'uncertain' => 0];
            foreach ($recipients as $email) {
                $counts[$ledger->status($email) ?? 'pending']++;
            }
            $this->line('Unieke geregistreerde ontvangers: '.$total);
            $this->line('Geselecteerd: '.count($recipients).'; open: '.$counts['pending'].'; geaccepteerd: '.$counts['accepted'].'; onzeker/geblokkeerd: '.$counts['uncertain']);
            $this->line('Mailer: '.config('mail.default').'; maximum: 1 poging per 2 seconden; geen queue/retries.');
            if ($this->option('dry-run') || (! $this->option('send') && $testTo === null)) {
                $this->info('DRY-RUN: geen e-mail verzonden.');

                return self::SUCCESS;
            }
            if ($testTo !== null && $this->option('send')) {
                throw new RuntimeException('Combineer --test-to niet met --send.');
            }
            if ($testTo === null && (string) $total !== (string) $this->option('expect')) {
                throw new RuntimeException('Aantal wijkt af van --expect of --expect ontbreekt; voer eerst een dry-run uit.');
            }
            if (config('mail.default') !== 'resend' || config('mail.mailers.resend.transport') !== 'resend' || ! filled(config('services.resend.key'))) {
                throw new RuntimeException('De verwachte Resend-configuratie ontbreekt.');
            }
            $budget = min($counts['pending'], $limit === null ? $counts['pending'] : (int) $limit);
            if ($counts['pending'] > 0) {
                $usage = Http::withToken(config('services.resend.key'))->timeout(15)->get('https://api.resend.com/usage');
                if (! $usage->successful()) {
                    $this->error('Providerquota niet controleerbaar; verzending geblokkeerd.');

                    return self::FAILURE;
                }
                foreach (['daily', 'monthly'] as $period) {
                    $quota = $usage->json('emails.'.$period);
                    if (! is_array($quota) || ! array_key_exists('limit', $quota) || ! is_numeric($quota['used'] ?? null)) {
                        throw new RuntimeException('Onbekende quota.');
                    }
                    if ($quota['limit'] !== null) {
                        if (! is_numeric($quota['limit'])) {
                            throw new RuntimeException('Onbekende quotalimiet.');
                        }
                        $remaining = max(0, (int) $quota['limit'] - (int) $quota['used']);
                        if ($limit === null && $remaining < $budget) {
                            $this->error('Onvoldoende Resend-quotum ('.$period.'); niets verzonden.');

                            return self::FAILURE;
                        }
                        $budget = min($budget, $remaining);
                    }
                }
            }
            $this->line('Verzendbudget deze uitvoering: '.$budget);
            $accepted = 0;
            foreach ($recipients as $email) {
                if ($accepted >= $budget) {
                    break;
                }
                if ($ledger->status($email) !== null) {
                    continue;
                }
                sleep(2);
                if (! $ledger->claim($email)) {
                    continue;
                }
                try {
                    $sent = Mail::mailer('resend')->to($email)->send(new PhpSecurityServiceMail);
                    $id = $sent?->getSymfonySentMessage()->getOriginalMessage()->getHeaders()->get('X-Resend-Email-ID')?->getBodyAsString();
                    if (! filled($id)) {
                        throw new RuntimeException('Geen providerbevestiging.');
                    }
                    $ledger->accepted($email, $id);
                    $accepted++;
                    $this->line('Geaccepteerd in deze uitvoering: '.$accepted);
                } catch (Throwable) {
                    // Do not log provider exceptions: they can contain addresses or credentials.
                    $this->error('Gestopt bij onzekere verzendpoging. Controleer Resend; deze ontvanger wordt niet automatisch opnieuw verstuurd.');

                    return self::FAILURE;
                }
            }
            $this->info('Klaar. Geaccepteerd in deze uitvoering: '.$accepted.'; eerder onzekere pogingen: '.$counts['uncertain']);
            $this->line('Nog open: '.($counts['pending'] - $accepted));

            return $counts['uncertain'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable) {
            $this->error('Veilig gestopt: controleer opties, ontvangersaantal, mailconfiguratie, inhoud en private opslag/lock. Geen foutdetails gelogd.');

            return self::FAILURE;
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
            umask($oldMask);
        }
    }
}
