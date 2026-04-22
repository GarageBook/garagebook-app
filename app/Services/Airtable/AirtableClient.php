<?php

namespace App\Services\Airtable;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class AirtableClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function findUserByRecordId(string $recordId): array
    {
        return $this->getRecord(config('airtable.users_table'), $recordId);
    }

    public function findUserByEmail(string $email): array
    {
        $response = $this->request()->get($this->usersUrl(), [
            'maxRecords' => 1,
            'filterByFormula' => sprintf('{%s} = "%s"', config('airtable.users_email_field'), $this->escapeFormulaValue($email)),
        ])->throw()->json();

        $record = $response['records'][0] ?? null;

        if (! $record) {
            throw new RuntimeException('Geen Airtable user gevonden voor dit e-mailadres.');
        }

        return $record;
    }

    public function listUsers(): array
    {
        $records = [];
        $offset = null;

        do {
            $query = [
                'pageSize' => 100,
                'view' => 'Grid view',
            ];

            if ($offset) {
                $query['offset'] = $offset;
            }

            $response = $this->request()->get($this->usersUrl(), $query)->throw()->json();

            $records = [...$records, ...($response['records'] ?? [])];
            $offset = $response['offset'] ?? null;
        } while ($offset);

        return $records;
    }

    public function getRecord(string $table, string $recordId): array
    {
        return $this->request()->get($this->tableUrl($table) . '/' . $recordId)->throw()->json();
    }

    private function request()
    {
        $token = config('airtable.personal_access_token');

        if (blank($token)) {
            throw new RuntimeException('AIRTABLE_PERSONAL_ACCESS_TOKEN is niet ingesteld.');
        }

        return $this->http
            ->baseUrl('https://api.airtable.com/v0')
            ->withToken($token)
            ->acceptJson();
    }

    private function usersUrl(): string
    {
        return $this->tableUrl(config('airtable.users_table'));
    }

    private function tableUrl(string $table): string
    {
        return sprintf('%s/%s', config('airtable.base_id'), rawurlencode($table));
    }

    private function escapeFormulaValue(string $value): string
    {
        return str_replace('"', '\"', $value);
    }
}
