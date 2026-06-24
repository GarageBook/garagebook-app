<?php

namespace App\Console\Commands;

use App\Services\Analytics\Ga4AnalyticsService;
use App\Services\Analytics\SearchConsoleService;
use Illuminate\Console\Command;

class DiagnoseGoogleOauthCommand extends Command
{
    protected $signature = 'garagebook:diagnose-google-oauth';

    protected $description = 'Diagnoseer Google OAuth config en refresh-token exchange zonder secrets te tonen.';

    public function handle(Ga4AnalyticsService $ga4, SearchConsoleService $searchConsole): int
    {
        $results = [
            'GA4' => $ga4->diagnoseOAuth(),
            'Search Console' => $searchConsole->diagnoseOAuth(),
        ];

        $failed = false;

        foreach ($results as $label => $diagnostics) {
            $this->line($label);
            $this->line(str_repeat('-', strlen($label)));
            $this->line('Auth mode: '.($diagnostics['auth_mode'] ?? 'unknown'));
            $this->line('Supports OAuth: '.$this->yesNo((bool) ($diagnostics['supports_oauth'] ?? false)));
            $this->line('Token endpoint: '.($diagnostics['token_uri'] ?? 'unknown'));
            $this->line('Scopes: '.implode(', ', $diagnostics['scopes'] ?? []));

            $this->line('Config aanwezig:');
            foreach (($diagnostics['config_present'] ?? []) as $envKey => $present) {
                $this->line('  - '.$envKey.': '.$this->yesNo((bool) $present));
            }

            if (($diagnostics['configuration_error'] ?? null) !== null) {
                $failed = true;
                $this->error('Configuratie: '.$diagnostics['configuration_error']);
                $this->newLine();

                continue;
            }

            $this->line('Token endpoint bereikbaar: '.$this->yesNo((bool) ($diagnostics['token_endpoint_reachable'] ?? false)));
            $this->line('HTTP status: '.(($diagnostics['http_status'] ?? null) ?: 'n.v.t.'));
            $this->line('Access token opgehaald: '.$this->yesNo((bool) ($diagnostics['token_exchange_success'] ?? false)));

            if (($diagnostics['google_error'] ?? null) !== null) {
                $failed = true;
                $this->error('Google error: '.$diagnostics['google_error']);
            }

            if (($diagnostics['google_error_description'] ?? null) !== null) {
                $failed = true;
                $this->error('Google error_description: '.$diagnostics['google_error_description']);
            }

            if (! (bool) ($diagnostics['token_exchange_success'] ?? false)) {
                $failed = true;
            }

            $this->newLine();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'ja' : 'nee';
    }
}
