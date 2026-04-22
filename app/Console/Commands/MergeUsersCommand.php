<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MergeUsersCommand extends Command
{
    protected $signature = 'users:merge
        {--from= : Bronuser e-mailadres}
        {--into= : Doeluser e-mailadres}
        {--force : Voer de merge echt uit}
        {--delete-source : Verwijder de bronuser na succesvolle merge}';

    protected $description = 'Consolideer twee users door voertuigen en Airtable-koppeling van bron naar doel te verplaatsen.';

    public function handle(): int
    {
        $fromEmail = strtolower(trim((string) $this->option('from')));
        $intoEmail = strtolower(trim((string) $this->option('into')));

        if ($fromEmail === '' || $intoEmail === '' || $fromEmail === $intoEmail) {
            $this->error('Geef twee verschillende e-mailadressen mee via --from en --into.');

            return self::FAILURE;
        }

        $source = User::query()->where('email', $fromEmail)->first();
        $target = User::query()->where('email', $intoEmail)->first();

        if (! $source || ! $target) {
            $this->error('Bron- of doeluser niet gevonden.');

            return self::FAILURE;
        }

        $summary = $this->preview($source, $target);

        foreach ($summary as $key => $value) {
            $this->line(sprintf('%s: %s', $key, json_encode($value)));
        }

        if (! $this->option('force')) {
            $this->warn('Dry run: geen wijzigingen opgeslagen. Gebruik --force om de merge uit te voeren.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($source, $target): void {
                $this->assertMergeIsSafe($source, $target);

                $sourceAirtableRecordId = $source->airtable_record_id;
                $sourceAirtableSyncedAt = $source->airtable_synced_at;
                $sourceEmailVerifiedAt = $source->email_verified_at;

                if ($sourceAirtableRecordId) {
                    $source->forceFill([
                        'airtable_record_id' => null,
                        'airtable_synced_at' => null,
                    ])->save();
                }

                $target->forceFill([
                    'is_admin' => $target->is_admin || $source->is_admin,
                    'airtable_record_id' => $target->airtable_record_id ?: $sourceAirtableRecordId,
                    'airtable_synced_at' => $target->airtable_synced_at ?: $sourceAirtableSyncedAt,
                    'email_verified_at' => $target->email_verified_at ?: $sourceEmailVerifiedAt,
                ])->save();

                DB::table('vehicles')
                    ->where('user_id', $source->id)
                    ->update(['user_id' => $target->id]);

                DB::table('sessions')
                    ->where('user_id', $source->id)
                    ->update(['user_id' => $target->id]);

                if ($this->option('delete-source')) {
                    $source->delete();
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('User merge voltooid.');

        return self::SUCCESS;
    }

    private function preview(User $source, User $target): array
    {
        return [
            'source_user_id' => $source->id,
            'target_user_id' => $target->id,
            'source_airtable_record_id' => $source->airtable_record_id,
            'target_airtable_record_id' => $target->airtable_record_id,
            'vehicles_to_move' => $source->vehicles()->count(),
            'source_is_admin' => (bool) $source->is_admin,
            'target_is_admin' => (bool) $target->is_admin,
            'will_delete_source' => (bool) $this->option('delete-source'),
        ];
    }

    private function assertMergeIsSafe(User $source, User $target): void
    {
        if ($target->airtable_record_id && $source->airtable_record_id && $target->airtable_record_id !== $source->airtable_record_id) {
            throw new RuntimeException('Doeluser heeft al een andere Airtable-koppeling. Merge afgebroken.');
        }
    }
}
