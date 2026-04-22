<?php

namespace App\Services\Airtable;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AirtableUserImporter
{
    public function __construct(
        private readonly AirtableClient $client,
    ) {}

    public function previewByRecordId(string $recordId): array
    {
        return $this->preview($this->client->findUserByRecordId($recordId));
    }

    public function previewByEmail(string $email): array
    {
        return $this->preview($this->client->findUserByEmail($email));
    }

    public function importByRecordId(string $recordId): array
    {
        return $this->persist($this->client->findUserByRecordId($recordId));
    }

    public function importByEmail(string $email): array
    {
        return $this->persist($this->client->findUserByEmail($email));
    }

    public function importRecord(array $record, ?string $plainPassword = null): array
    {
        return $this->persist($record, $plainPassword);
    }

    private function preview(array $record): array
    {
        [$payload, $user, $action] = $this->mapRecord($record);

        return [
            'action' => $action,
            'record_id' => $record['id'],
            'name' => $payload['name'],
            'email' => $payload['email'],
            'matched_user_id' => $user?->id,
            'matched_user_email' => $user?->email,
            'will_generate_password' => $user === null,
        ];
    }

    private function persist(array $record, ?string $plainPassword = null): array
    {
        [$payload, $user, $action, $generatedPassword] = $this->mapRecord($record, $plainPassword);

        $user = DB::transaction(function () use ($payload, $user) {
            if ($user) {
                $user->fill(Arr::except($payload, ['password']));
                $user->save();

                return $user->refresh();
            }

            return User::query()->create($payload);
        });

        return [
            'action' => $action,
            'record_id' => $record['id'],
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'password_generated' => $action === 'created',
            'plain_password' => $generatedPassword,
        ];
    }

    private function mapRecord(array $record, ?string $plainPassword = null): array
    {
        $fields = $record['fields'] ?? [];
        $name = trim((string) data_get($fields, config('airtable.users_name_field')));
        $airtableEmail = strtolower(trim((string) data_get($fields, config('airtable.users_email_field'))));

        if ($name === '' || $airtableEmail === '') {
            throw new RuntimeException('Airtable user record mist verplichte naam- of e-mailvelden.');
        }

        $user = User::query()
            ->where('airtable_record_id', $record['id'])
            ->first();

        if (! $user) {
            $user = User::query()
                ->where('email', $airtableEmail)
                ->first();
        }

        $action = $user ? 'updated' : 'created';
        $payload = [
            'name' => $name,
            'email' => $user?->email ?: $airtableEmail,
            'airtable_record_id' => $record['id'],
            'airtable_synced_at' => now(),
        ];

        $generatedPassword = null;

        if (! $user) {
            $generatedPassword = $plainPassword ?: Str::password(20);
            $payload['password'] = Hash::make($generatedPassword);
        }

        return [$payload, $user, $action, $generatedPassword];
    }
}
