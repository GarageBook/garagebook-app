<?php

namespace App\Services\Outreach;

use App\Models\OutreachProspect;
use Illuminate\Database\Eloquent\Builder;

class OutreachProspectExportService
{
    public function toCsv(Builder $query): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $this->headers());

        $query->orderBy('id')->chunk(200, function ($prospects) use ($handle): void {
            foreach ($prospects as $prospect) {
                /** @var OutreachProspect $prospect */
                fputcsv($handle, [
                    $prospect->company_name,
                    $prospect->city,
                    $prospect->email,
                    $prospect->website,
                    $prospect->demoUrl(),
                    $this->formatDate($prospect->clicked_at),
                    $this->formatDate($prospect->first_login_at),
                    $this->formatDate($prospect->last_login_at),
                    $prospect->login_count,
                ]);
            }
        });

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }

    private function headers(): array
    {
        return [
            'company_name',
            'city',
            'email',
            'website',
            'demo_url',
            'clicked_at',
            'first_login_at',
            'last_login_at',
            'login_count',
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        return $value?->format('Y-m-d H:i:s');
    }
}
