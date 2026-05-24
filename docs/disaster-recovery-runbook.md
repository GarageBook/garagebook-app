# GarageBook Disaster Recovery Runbook

This runbook is for a full GarageBook recovery when the live server is unavailable, corrupted, or must be rebuilt from scratch.

Current production assumptions:

- hosting: Hetzner
- server management: Laravel Forge
- application: Laravel 13 / Filament 5
- database: MySQL / MariaDB
- offsite backup target: Backblaze B2 bucket `garagebook-prod-backups`
- backup prefix: `daily/`

## Recovery Goal

Recover GarageBook from a full server loss with:

- application code redeployed from git
- latest suitable offsite restore point restored from Backblaze B2
- web, queue, scheduler, and storage working again

## Access Required

Before starting, confirm access to:

- Hetzner account
- Laravel Forge account
- GitHub repository
- Backblaze B2 account
- DNS provider
- GarageBook production secrets

## Data Required

Each restore point should contain:

- `database-*.sql`
- `env-*.backup`
- `storage-*.tar.gz`
- `server-config-*.tar.gz`
- `manifest-*.json`

## Phase 1: Create Replacement Server

1. Create a new Ubuntu server in Hetzner.
2. Add the server to Laravel Forge.
3. Provision the server with the same PHP version and stack used by production.
4. Create the GarageBook site in Forge.
5. Point the site at the GitHub repository.
6. Deploy the site once so Forge creates the base release structure.

## Phase 2: Confirm Base Services

On the new server, confirm:

- Nginx is installed
- PHP-FPM is installed
- MySQL or MariaDB is installed
- the `forge` user exists
- `mysqldump` and `mysql` clients are available

Useful checks:

```bash
php -v
mysql --version
mysqldump --version
sudo -u forge crontab -l
```

## Phase 3: Fetch Restore Point

In Backblaze B2:

1. Open bucket `garagebook-prod-backups`.
2. Browse `daily/`.
3. Choose the most appropriate restore point.

Choose the latest successful restore point unless there is a known bad deployment or data issue.

Download these files from the chosen restore point:

- `database-...sql`
- `env-...backup`
- `storage-...tar.gz`
- `server-config-...tar.gz`
- `manifest-...json`

## Phase 4: Restore Application Secrets

Copy the backed up env file into place:

```bash
cd /home/forge/app.garagebook.nl/current
cp /path/to/env-YYYY-MM-DD.backup .env
```

Then review `.env` before going live:

- database host, database name, username, password
- app URL
- mail settings
- Backblaze backup key values
- any third-party API tokens

## Phase 5: Restore Database

Create the production database if needed, then import the dump:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS garagebook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p garagebook < /path/to/database-YYYY-MM-DD.sql
```

If production uses a different database name, use that name instead of `garagebook`.

After import, confirm that key tables exist:

```bash
mysql -u root -p -e "USE garagebook; SHOW TABLES;"
```

## Phase 6: Restore Storage

Restore the Laravel storage tree at `/` so the archived absolute paths land correctly:

```bash
tar -xzf /path/to/storage-YYYY-MM-DD.tar.gz -C /
```

Then verify expected paths:

```bash
ls -la /home/forge/app.garagebook.nl/current/storage
ls -la /home/forge/app.garagebook.nl/current/storage/app
ls -la /home/forge/app.garagebook.nl/current/storage/app/public
```

## Phase 7: Restore Server Config

If needed, restore the archived server-level files:

```bash
tar -xzf /path/to/server-config-YYYY-MM-DD.tar.gz -C /
```

Review before restarting services:

- `/etc/nginx`
- `/etc/systemd/system`
- `/etc/cron.d`

Do not blindly overwrite unrelated newer server config without checking it first.

## Phase 8: Fix Permissions And Clear Laravel State

Run:

```bash
cd /home/forge/app.garagebook.nl/current
sudo chown -R forge:forge /home/forge/app.garagebook.nl/current
sudo chmod -R ug+rw storage bootstrap/cache
php artisan optimize:clear
php artisan storage:link
```

If the app runs under a different web group, adjust ownership accordingly.

## Phase 9: Restart Services

Restart the application stack:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

If queue workers are managed with systemd or Supervisor, restart them too.

After a normal deploy, also restart Laravel queue workers so new job code is picked up:

```bash
cd /home/forge/app.garagebook.nl/current
php artisan queue:restart
```

Examples:

```bash
sudo systemctl restart supervisor
sudo supervisorctl status
```

or:

```bash
sudo systemctl daemon-reload
sudo systemctl restart garagebook-worker.service
```

Use the real service names from production.

## Phase 10: Re-enable Scheduler

Confirm the Forge user cron still exists:

```bash
sudo -u forge crontab -l
```

You want at least:

```cron
* * * * * cd /home/forge/app.garagebook.nl/current && php artisan schedule:run >> /dev/null 2>&1
```

## Phase 11: Functional Verification

Before declaring the restore complete, verify:

- homepage loads
- admin login works
- dashboard loads
- blog images load
- vehicle/media uploads resolve
- one write action succeeds, for example editing a maintenance log
- queue-driven behavior works if applicable

Useful commands:

```bash
cd /home/forge/app.garagebook.nl/current
php artisan about
php artisan migrate:status
php artisan backup:run-disaster-recovery
```

The final backup command confirms the restored server can also create a fresh offsite backup again.

## Phase 12: DNS And TLS Sanity Check

If the new server has a different IP:

- update DNS records
- verify Forge site IP
- verify SSL certificate status

Then confirm public access:

- `https://app.garagebook.nl`
- any public marketing pages still routed by the server

## Decision Rules During Incident

- Prefer the newest known-good restore point, not blindly the newest restore point.
- If the latest restore point was created after a bad deploy or data corruption event, go one step older.
- Do not rotate secrets until the restore is stable unless secret compromise is part of the incident.
- If only the app code is broken and the server is healthy, use normal deploy rollback instead of full disaster recovery.

## Post-Recovery Checklist

After service is restored:

1. Confirm Hetzner backups are enabled on the replacement server.
2. Confirm Backblaze B2 backups succeed again.
3. Confirm scheduler and queue workers survive reboot.
4. Rotate any secrets that may have been exposed during the incident.
5. Record which restore point was used and why.
6. Review root cause so the incident is less likely to recur.
