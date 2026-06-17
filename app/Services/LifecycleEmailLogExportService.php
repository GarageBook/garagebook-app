<?php

namespace App\Services;

use App\Models\LifecycleEmailLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LifecycleEmailLogExportService
{
    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'id',
            'created_at',
            'sent_at',
            'user_id',
            'user_name',
            'user_email',
            'email_key',
            'status',
            'reason_skipped',
            'error_message',
            'vehicles_count',
            'maintenance_logs_count',
            'documents_count',
            'last_login_at',
            'clicked_at',
            'goal_completed_at',
        ];
    }

    public function toCsv(Builder $query): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $this->headers());

        foreach ($query->cursor() as $log) {
            if (! $log instanceof LifecycleEmailLog) {
                continue;
            }

            $user = $log->relationLoaded('user') ? $log->user : $log->user()->withDefault()->first();

            fputcsv($handle, [
                $log->id,
                $this->formatDate($log->created_at),
                $this->formatDate($log->sent_at),
                $log->user_id,
                (string) ($user?->name ?? 'Onbekende gebruiker'),
                (string) ($user?->email ?? '-'),
                $log->email_key,
                $log->status,
                $log->reason_skipped,
                $log->error_message,
                $log->vehicles_count,
                $log->maintenance_logs_count,
                $log->documents_count,
                $this->formatDate($log->last_login_at),
                $this->formatDate($log->clicked_at),
                $this->formatDate($log->goal_completed_at),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return "\xEF\xBB\xBF".$csv;
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
