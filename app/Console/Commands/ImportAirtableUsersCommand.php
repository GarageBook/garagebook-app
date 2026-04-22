<?php

namespace App\Console\Commands;

use App\Services\Airtable\AirtableUserImporter;
use App\Services\Airtable\AirtableUserDataImporter;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class ImportAirtableUsersCommand extends Command
{
    protected $signature = 'airtable:import-users
        {--record= : Airtable record id, bijvoorbeeld rec123}
        {--email= : E-mailadres om in Airtable Users op te zoeken}
        {--with-related : Importeer ook gekoppelde voertuigen en onderhoud}
        {--force : Schrijf wijzigingen echt weg}
        {--dry-run : Toon alleen wat er zou gebeuren}';

    protected $description = 'Importeer een enkele user vanuit Airtable Users naar de lokale users-tabel.';

    public function handle(AirtableUserImporter $importer, AirtableUserDataImporter $dataImporter): int
    {
        $recordId = $this->option('record');
        $email = $this->option('email');

        if ((blank($recordId) && blank($email)) || (filled($recordId) && filled($email))) {
            $this->error('Geef precies een selector mee: --record of --email.');

            return self::FAILURE;
        }

        $shouldPersist = (bool) $this->option('force');
        $withRelated = (bool) $this->option('with-related');

        if ($withRelated && ! $shouldPersist) {
            $this->error('--with-related vereist --force.');

            return self::FAILURE;
        }

        try {
            $result = filled($recordId)
                ? ($shouldPersist ? $importer->importByRecordId($recordId) : $importer->previewByRecordId($recordId))
                : ($shouldPersist ? $importer->importByEmail($email) : $importer->previewByEmail($email));

            if ($withRelated && $shouldPersist) {
                $user = User::query()->findOrFail($result['user_id']);
                $result = array_merge($result, $dataImporter->importForUser($user));
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $shouldPersist) {
            $this->warn('Dry run: geen wijzigingen opgeslagen. Gebruik --force om de import echt uit te voeren.');
        }

        foreach ($result as $key => $value) {
            $this->line(sprintf('%s: %s', $key, json_encode($value)));
        }

        if ($shouldPersist && ($result['password_generated'] ?? false)) {
            $this->warn('Er is een random wachtwoord gezet. Gebruik daarna de wachtwoord-resetflow voor eerste login.');
        }

        return self::SUCCESS;
    }
}
