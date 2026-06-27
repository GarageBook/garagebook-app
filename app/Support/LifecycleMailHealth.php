<?php

namespace App\Support;

use RuntimeException;

class LifecycleMailHealth
{
    /**
     * @var callable(string): bool
     */
    private $classExists;

    public function __construct(?callable $classExists = null)
    {
        $this->classExists = $classExists ?? static fn (string $class): bool => class_exists($class);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $appEnv = (string) config('app.env');
        $mailer = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$mailer}.transport");
        $resendKeyPresent = filled(config('services.resend.key'));
        $resendClassAutoloadable = ($this->classExists)('Resend');
        $composerLockHasResend = $this->composerLockHasResend();
        $vendorHasResend = $this->vendorHasResend();
        $production = $appEnv === 'production';

        $checks = [
            'app_env' => [
                'ok' => filled($appEnv),
                'label' => 'APP_ENV',
                'value' => $appEnv ?: '(missing)',
                'severity' => filled($appEnv) ? 'info' : 'error',
            ],
            'mail_mailer' => [
                'ok' => filled($mailer),
                'label' => 'MAIL_MAILER',
                'value' => $mailer ?: '(missing)',
                'severity' => filled($mailer) ? 'info' : 'error',
            ],
            'production_not_log_mailer' => [
                'ok' => ! $production || ($mailer !== 'log' && $transport !== 'log'),
                'label' => 'Production mailer is not log',
                'value' => $production ? "mailer={$mailer}, transport={$transport}" : 'not production',
                'severity' => $production && ($mailer === 'log' || $transport === 'log') ? 'error' : 'info',
            ],
            'resend_api_key' => [
                'ok' => ! $production || ! $this->usesResend($mailer, $transport) || $resendKeyPresent,
                'label' => 'RESEND_API_KEY present',
                'value' => $resendKeyPresent ? 'yes' : 'no',
                'severity' => $production && $this->usesResend($mailer, $transport) && ! $resendKeyPresent ? 'error' : 'info',
            ],
            'resend_class' => [
                'ok' => ! $production || $resendClassAutoloadable,
                'label' => "class_exists('Resend')",
                'value' => $resendClassAutoloadable ? 'yes' : 'no',
                'severity' => $production && ! $resendClassAutoloadable ? 'error' : 'info',
            ],
            'composer_lock_resend' => [
                'ok' => $composerLockHasResend,
                'label' => 'composer.lock contains resend/resend-php',
                'value' => $composerLockHasResend ? 'yes' : 'no',
                'severity' => $composerLockHasResend ? 'info' : 'warning',
            ],
            'vendor_resend' => [
                'ok' => $vendorHasResend,
                'label' => 'vendor contains resend/resend-php',
                'value' => $vendorHasResend ? 'yes' : 'no',
                'severity' => $vendorHasResend ? 'info' : 'warning',
            ],
            'config_cached' => [
                'ok' => true,
                'label' => 'Config cached',
                'value' => app()->configurationIsCached() ? 'yes' : 'no',
                'severity' => 'info',
            ],
        ];

        return [
            'app_env' => $appEnv,
            'mailer' => $mailer,
            'transport' => $transport,
            'release_path' => $this->releasePath(),
            'healthy' => collect($checks)->doesntContain(fn (array $check): bool => $check['severity'] === 'error' && ! $check['ok']),
            'checks' => $checks,
        ];
    }

    public function assertReadyForLifecycleDelivery(): void
    {
        $report = $this->report();

        if ($report['healthy']) {
            return;
        }

        $messages = collect($report['checks'])
            ->filter(fn (array $check): bool => $check['severity'] === 'error' && ! $check['ok'])
            ->map(fn (array $check): string => $check['label'].': '.$check['value'])
            ->implode('; ');

        throw new RuntimeException('Lifecycle mailconfig is ongezond: '.$messages);
    }

    /**
     * @return array<string, mixed>
     */
    public function logContext(?int $queueJobId = null, ?int $retryOfLogId = null, ?string $resendMessageId = null): array
    {
        $report = $this->report();

        return [
            'mailer' => $report['mailer'],
            'mail_transport' => $report['transport'],
            'release_path' => $report['release_path'],
            'queue_job_id' => $queueJobId,
            'retry_of_log_id' => $retryOfLogId,
            'resend_message_id' => $resendMessageId,
        ];
    }

    public function releasePath(): ?string
    {
        $releasePath = env('RELEASE_PATH') ?: env('CURRENT_RELEASE') ?: null;

        return filled($releasePath) ? (string) $releasePath : null;
    }

    private function usesResend(string $mailer, string $transport): bool
    {
        return $mailer === 'resend' || $transport === 'resend';
    }

    private function composerLockHasResend(): bool
    {
        $path = base_path('composer.lock');

        return is_file($path) && str_contains((string) file_get_contents($path), '"name": "resend/resend-php"');
    }

    private function vendorHasResend(): bool
    {
        return is_file(base_path('vendor/resend/resend-php/src/Resend.php'));
    }
}
