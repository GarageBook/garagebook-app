<?php

return [
    'enabled' => env('BACKUP_ENABLED', false),
    'schedule_at' => env('BACKUP_SCHEDULE_AT', '02:30'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),
    'remote_prefix' => trim((string) env('BACKUP_REMOTE_PREFIX', 'daily'), '/'),
    'mysql_dump_binary' => env('BACKUP_MYSQL_DUMP_BINARY', 'mysqldump'),
    'extra_paths' => array_values(array_filter(array_map(
        static fn (string $path): string => trim($path),
        explode(',', (string) env('BACKUP_EXTRA_PATHS', ''))
    ))),
];
