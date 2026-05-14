# Analytics dashboard

Deze admin-only dashboardlaag leest uitsluitend uit lokaal opgeslagen analyticsdata. De Google API's worden niet bij iedere pageload aangesproken.

## Vereiste Google API's

Zet in Google Cloud aan:
- Google Analytics Data API
- Google Search Console API

## Service account

1. Maak een service account aan in Google Cloud.
2. Download het JSON-credentialsbestand.
3. Sla het bestand lokaal en live buiten Git op.

Aanbevolen paden:
- `storage/app/google/ga4-service-account.json`
- `storage/app/google/search-console-service-account.json`

Deze paden staan in `.gitignore` en mogen nooit gecommit worden.

## Toegang geven

### GA4

Geef het service account toegang tot de juiste GA4 property met minimaal leesrechten.

### Search Console

Geef hetzelfde service account toegang tot de juiste Search Console property met minimaal leesrechten.

Let op:
- `GOOGLE_SEARCH_CONSOLE_SITE_URL` moet exact overeenkomen met de property.
- Voorbeelden:
  - `https://garagebook.nl/`
  - `sc-domain:garagebook.nl`

## Benodigde env variabelen

Zet lokaal en live:

```dotenv
GOOGLE_ANALYTICS_PROPERTY_ID=
GOOGLE_ANALYTICS_CREDENTIALS_JSON=storage/app/google/ga4-service-account.json
GOOGLE_SEARCH_CONSOLE_SITE_URL=https://garagebook.nl/
GOOGLE_SEARCH_CONSOLE_CREDENTIALS_JSON=storage/app/google/search-console-service-account.json
```

## Synchronisatiecommando's

Dagelijkse sync:

```bash
php artisan garagebook:sync-ga4-analytics
php artisan garagebook:sync-search-console
```

Eerste backfill:

```bash
php artisan garagebook:sync-ga4-analytics --from=2026-05-01 --to=2026-05-13
php artisan garagebook:sync-search-console --from=2026-05-01 --to=2026-05-11
```

Default gedrag:
- GA4 haalt standaard gisteren op
- Search Console haalt standaard drie dagen geleden op

## Scheduler

De scheduler draait automatisch:
- GA4 rond `04:00`
- Search Console rond `04:15`

Beide jobs draaien met `withoutOverlapping()`.

## Dashboardgedrag

- Widgets zijn alleen zichtbaar voor admins via `User::isAdmin()`.
- Widgets lezen alleen uit de lokale database.
- Als credentials ontbreken of nog geen sync heeft gedraaid, tonen de widgets een nette empty state:

`Nog geen analyticsdata beschikbaar. Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.`
