<?php

// Standalone integration test because production has no dev dependencies.
// No production database connection or real mail transport is used.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Mail\PhpSecurityServiceMail;
use App\Support\ServiceMailLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

$root = sys_get_temp_dir().'/garagebook-service-test-'.bin2hex(random_bytes(8));
mkdir($root, 0700);
mkdir($root.'/views', 0700);
putenv('APP_CONFIG_CACHE='.$root.'/no-config.php');
putenv('APP_ENV=testing');
putenv('VIEW_COMPILED_PATH='.$root.'/views');
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->useStoragePath($root);
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, 'FAIL: '.$exception->getMessage().PHP_EOL);
    exit(1);
});
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null,
    'view.compiled' => $root.'/views',
    'mail.default' => 'resend',
    'mail.mailers.resend.transport' => 'resend',
    'mail.from' => ['address' => 'sender@example.test', 'name' => 'GarageBook'],
    'services.resend.key' => 'fake-test-key',
    'logging.default' => 'null',
    'cache.default' => 'array',
]);
DB::purge('sqlite');
DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
foreach ([' A@example.test ', 'a@example.test', 'b@example.test', 'c@example.test', '', null, 'bad-address'] as $email) {
    DB::table('users')->insert(['email' => $email]);
}
Http::preventStrayRequests();
$limit = 1000;
Http::fake(['api.resend.com/usage' => function () use (&$limit) {
    return Http::response(['emails' => ['daily' => ['limit' => $limit, 'used' => 0], 'monthly' => ['limit' => 3000, 'used' => 0]]]);
}]);
$quota = function (int $value) use (&$limit): void {
    $limit = $value;
};
$quota(1000);
$transport = new class extends AbstractTransport
{
    public array $attempts = [];

    public ?int $failAt = null;

    protected function doSend(SentMessage $message): void
    {
        $this->attempts[] = $message->getEnvelope()->getRecipients()[0]->getAddress();
        if (count($this->attempts) === $this->failAt) {
            throw new RuntimeException('Simulated timeout with private address');
        }
        $message->getOriginalMessage()->getHeaders()->addTextHeader('X-Resend-Email-ID', 'fake-'.count($this->attempts));
    }

    public function __toString(): string
    {
        return 'fake-resend';
    }
};
Mail::extend('resend', fn () => $transport);
$checks = 0;
$check = function (bool $ok, string $label) use (&$checks): void {
    if (! $ok) {
        throw new RuntimeException('FAIL: '.$label);
    }
    $checks++;
    echo 'PASS: '.$label.PHP_EOL;
};
$run = fn (array $options = []) => Artisan::call('garagebook:php-security-service-mail', $options);
$check(ServiceMailLedger::recipients([' A@EXAMPLE.test ', 'a@example.test', '', null, 'bad']) === ['a@example.test'], 'validatie en deduplicatie');
$mail = new PhpSecurityServiceMail;
$html = $mail->render();
foreach (explode("\n\n", PhpSecurityServiceMail::BODY) as $paragraph) {
    $check(str_contains($html, $paragraph), 'HTML bevat berichtparagraaf');
}
$check(trim(view('emails.php-security-service-text', ['body' => PhpSecurityServiceMail::BODY])->render()) === PhpSecurityServiceMail::BODY, 'exacte platte tekst');
$check($mail->envelope()->subject === 'GarageBook vanmiddag tijdelijk niet bereikbaar', 'exact onderwerp');
$check($run() === 0 && str_contains(Artisan::output(), 'ontvangers: 3') && count($transport->attempts) === 0, 'standaard dry-run zonder verzending');
$check($run(['--send' => true, '--expect' => 2]) === 1 && count($transport->attempts) === 0, 'afwijkend aantal blokkeert');
$quota(2);
$check($run(['--send' => true, '--expect' => 3]) === 1 && count($transport->attempts) === 0, 'onvoldoende dagquotum blokkeert gehele verzending');
$quota(1000);
$check($run(['--test-to' => 'A@example.test']) === 0 && $transport->attempts === ['a@example.test'], 'test verstuurt uitsluitend naar expliciet adres');
$check($run(['--test-to' => 'A@example.test']) === 0 && count($transport->attempts) === 1, 'test herhalen verstuurt niet opnieuw');
$transport->failAt = 2;
$check($run(['--send' => true, '--expect' => 3]) === 1 && count($transport->attempts) === 2, 'gedeeltelijke verzending stopt bij timeout');
$check(! str_contains(Artisan::output(), 'private address') && ! str_contains(Artisan::output(), '@'), 'geen persoonsgegevens of exceptiontekst in uitvoer');
$transport->failAt = null;
$check($run(['--send' => true, '--expect' => 3]) === 1 && $transport->attempts === ['a@example.test', 'b@example.test', 'c@example.test'], 'herstart hervat restant en slaat bevestigd/onzeker over');
$run(['--send' => true, '--expect' => 3]);
$check(count($transport->attempts) === 3, 'herhaalde bulkactie verstuurt niets dubbel');
$lock = fopen($root.'/app/private/service-mail-php-security-2026-10-21/run.lock', 'c');
flock($lock, LOCK_EX);
$check($run() === 1, 'gelijktijdige uitvoering geblokkeerd');
fclose($lock);
foreach (['d@example.test', 'e@example.test', 'f@example.test'] as $email) {
    DB::table('users')->insert(['email' => $email]);
}
$quota(1);
$run(['--send' => true, '--expect' => 6, '--limit' => 3]);
$check(count($transport->attempts) === 4 && end($transport->attempts) === 'd@example.test', 'deelverzending begrensd tot beschikbaar quotum');
$quota(0);
$run(['--send' => true, '--expect' => 6, '--limit' => 3]);
$check(count($transport->attempts) === 4, 'uitgeput quotum verstuurt niets en claimt geen ontvangers');
$quota(1000);
$run(['--send' => true, '--expect' => 6, '--limit' => 1]);
$check(count($transport->attempts) === 5 && end($transport->attempts) === 'e@example.test', 'expliciete maximum blijft gelden bij ruim providerquotum');
$run(['--send' => true, '--expect' => 6, '--limit' => 1]);
$check(count($transport->attempts) === 6 && end($transport->attempts) === 'f@example.test', 'deelverzending hervat bij volgende open ontvanger');
$check($run(['--send' => true, '--expect' => 6, '--limit' => '-1']) === 1 && count($transport->attempts) === 6, 'ongeldige limiet geblokkeerd');
$ledger = new ServiceMailLedger($root.'/claims.sqlite', 'fixed');
$check($ledger->claim('new@example.test'), 'claim duurzaam vastgelegd vóór verzending');
unset($ledger);
$ledger = new ServiceMailLedger($root.'/claims.sqlite', 'fixed');
$check(! $ledger->claim('new@example.test') && $ledger->status('new@example.test') === 'uncertain', 'procesherstart na claim kan niet dubbel versturen');
try {
    new ServiceMailLedger($root.'/claims.sqlite', 'changed');
    $check(false, 'inhoudswijziging blokkeert');
} catch (RuntimeException) {
    $check(true, 'inhoudswijziging blokkeert');
}
echo $checks.' controles geslaagd; uitsluitend SQLite in geheugen, fake HTTP en fake mail.'.PHP_EOL;
