# GarageBook Backup And Recovery

This repository now uses `php artisan backup:run-disaster-recovery` for the offsite disaster recovery backup flow.

## Scope

Each restore point uploads these artifacts to the configured `backups` disk:

- a database backup for the active driver
  - `sqlite` via `VACUUM INTO`
  - `mysql`/`mariadb` via `mysqldump`
- a copy of `.env`
- a tarball of the full project `storage/` directory
- an optional tarball of extra absolute server paths from `BACKUP_EXTRA_PATHS`
- a manifest with checksums, app metadata, and the live git commit

## Required Environment Variables

Set these on the live server:

```dotenv
BACKUP_ENABLED=true
BACKUP_SCHEDULE_AT=02:30
BACKUP_RETENTION_DAYS=7
BACKUP_REMOTE_PREFIX=daily
BACKUP_MYSQL_DUMP_BINARY=mysqldump
BACKUP_EXTRA_PATHS=/etc/nginx,/etc/systemd/system,/etc/cron.d

BACKUP_AWS_ACCESS_KEY_ID=
BACKUP_AWS_SECRET_ACCESS_KEY=
BACKUP_AWS_DEFAULT_REGION=eu-central-003
BACKUP_AWS_BUCKET=garagebook-prod-backups
BACKUP_AWS_ENDPOINT=https://s3.eu-central-003.backblazeb2.com
BACKUP_AWS_USE_PATH_STYLE_ENDPOINT=false
```

`BACKUP_EXTRA_PATHS` is intentionally explicit. If you want full server recovery, include the real paths that matter on the live server. The command fails when a configured path is missing or unreadable so the backup does not silently give false confidence.

For `mysql` or `mariadb` production environments, the server also needs the configured dump binary available on the `PATH`.

## Scheduler

Laravel schedules the command automatically when `BACKUP_ENABLED=true`, but the server still needs the standard cron entry:

```cron
* * * * * cd /home/willem/garagebook && php artisan schedule:run >> /dev/null 2>&1
```

## Manual Run

```bash
php artisan backup:run-disaster-recovery
```

Optional retention override:

```bash
php artisan backup:run-disaster-recovery --keep=7
```

## Restore Outline

1. Create a new Hetzner server.
2. Deploy the GarageBook code from git.
3. Download one restore point from Backblaze B2.
4. Restore `.env`.
5. Restore `database-*.sqlite` to `database/database.sqlite`.
6. Extract `storage-*.tar.gz` at `/`.
7. Extract `server-config-*.tar.gz` at `/` if present.
8. Run `php artisan optimize:clear`.
9. Restart the web server, PHP-FPM, and queue workers.
10. Validate login, admin, uploads, blog images, and a write path such as a maintenance log change.

## Legacy Scripts

The old repository-level `backup.sh` and `restore-latest.sh` were local-only helpers. They are no longer authoritative for disaster recovery because they did not create an offsite restore point and did not safely cover full server recovery.
