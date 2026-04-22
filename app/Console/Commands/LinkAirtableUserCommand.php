<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class LinkAirtableUserCommand extends Command
{
    protected $signature = 'users:link-airtable
        {--email= : Lokaal e-mailadres}
        {--record= : Airtable record id}
        {--force : Sla de koppeling echt op}';

    protected $description = 'Koppel een bestaand lokaal account aan een Airtable userrecord.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $recordId = trim((string) $this->option('record'));

        if ($email === '' || $recordId === '') {
            $this->error('Geef --email en --record mee.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error('User niet gevonden.');

            return self::FAILURE;
        }

        $this->line(sprintf('user_id: %s', json_encode($user->id)));
        $this->line(sprintf('email: %s', json_encode($user->email)));
        $this->line(sprintf('current_airtable_record_id: %s', json_encode($user->airtable_record_id)));
        $this->line(sprintf('new_airtable_record_id: %s', json_encode($recordId)));

        if (! $this->option('force')) {
            $this->warn('Dry run: geen wijzigingen opgeslagen. Gebruik --force om de koppeling op te slaan.');

            return self::SUCCESS;
        }

        $user->forceFill([
            'airtable_record_id' => $recordId,
        ])->save();

        $this->info('Airtable-koppeling opgeslagen.');

        return self::SUCCESS;
    }
}
