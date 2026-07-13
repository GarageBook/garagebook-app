<?php

namespace App\Services\Growth\Motorclubs;

class MotorclubImportResult
{
    /**
     * @var array<string, int>
     */
    public array $summary = [
        'read' => 0,
        'valid' => 0,
        'create' => 0,
        'existing' => 0,
        'duplicates' => 0,
        'updates' => 0,
        'manual_review' => 0,
        'invalid' => 0,
        'excluded' => 0,
        'public_email' => 0,
        'missing_email' => 0,
        'email_present' => 0,
        'personal_email' => 0,
        'queued' => 0,
        'sent' => 0,
    ];

    /**
     * @var array<string, int>
     */
    public array $campaigns = [];

    /**
     * @var array<string, int>
     */
    public array $subtypes = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $records = [];

    /**
     * @var array<int, array<string, string>>
     */
    public array $sourceInconsistencies = [];

    /**
     * @var array<int, string>
     */
    public array $errors = [];

    public function increment(string $key, int $amount = 1): void
    {
        $this->summary[$key] = ($this->summary[$key] ?? 0) + $amount;
    }

    public function incrementCampaign(?string $campaign): void
    {
        $campaign ??= '(missing)';
        $this->campaigns[$campaign] = ($this->campaigns[$campaign] ?? 0) + 1;
    }

    public function incrementSubtype(?string $subtype): void
    {
        $subtype ??= '(missing)';
        $this->subtypes[$subtype] = ($this->subtypes[$subtype] ?? 0) + 1;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function addRecord(array $record): void
    {
        $this->records[] = $record;
    }

    public function addSourceInconsistency(string $name, string $field, string $csvValue, string $markdownValue): void
    {
        $this->sourceInconsistencies[] = [
            'name' => $name,
            'field' => $field,
            'csv' => $csvValue,
            'markdown' => $markdownValue,
        ];
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }
}
