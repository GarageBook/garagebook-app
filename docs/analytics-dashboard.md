# Analytics dashboard

Deze admin-only dashboardlaag leest uitsluitend uit lokaal opgeslagen analyticsdata. De Google API's worden niet bij iedere pageload aangesproken.

## Vereiste Google API's

Zet in Google Cloud aan:
- Google Analytics Data API
- Google Search Console API

## GA4 authenticatie

GA4 ondersteunt nu twee authenticatiemodi:
- `service_account`: bestaande setup met JSON-bestand
- `oauth`: user credentials via een Google account zoals `garagebook.analytics@gmail.com`

### GA4 via OAuth user credentials

Gebruik dit wanneer de GA4 property alleen toegankelijk is via een normaal Google account.

Benodigd:
- een OAuth client in Google Cloud
- een refresh token voor `garagebook.analytics@gmail.com`
- toegang van dat Google account tot de juiste GA4 property met minimaal leesrechten

Benodigde env variabelen:

```dotenv
GOOGLE_ANALYTICS_AUTH_MODE=oauth
GOOGLE_ANALYTICS_CLIENT_ID=
GOOGLE_ANALYTICS_CLIENT_SECRET=
GOOGLE_ANALYTICS_REFRESH_TOKEN=
GOOGLE_ANALYTICS_PROPERTY_ID=
```

### GA4 via service account

1. Maak een service account aan in Google Cloud.
2. Download het JSON-credentialsbestand.
3. Sla het bestand lokaal en live buiten Git op.

Aanbevolen pad:
- `storage/app/google/ga4-service-account.json`

Benodigde env variabelen:

```dotenv
GOOGLE_ANALYTICS_AUTH_MODE=service_account
GOOGLE_ANALYTICS_PROPERTY_ID=
GOOGLE_ANALYTICS_CREDENTIALS_JSON=storage/app/google/ga4-service-account.json
```

## Search Console service account

Search Console gebruikt nog steeds alleen service-account authenticatie.

1. Maak een service account aan in Google Cloud.
2. Download het JSON-credentialsbestand.
3. Sla het bestand lokaal en live buiten Git op.

Aanbevolen pad:
- `storage/app/google/search-console-service-account.json`

Deze paden staan in `.gitignore` en mogen nooit gecommit worden.

## Toegang geven

### GA4

Geef afhankelijk van de gekozen auth-methode toegang tot de juiste GA4 property met minimaal leesrechten:
- bij `oauth`: het Google user account, bijvoorbeeld `garagebook.analytics@gmail.com`
- bij `service_account`: het service account

### Search Console

Geef het Search Console service account toegang tot de juiste Search Console property met minimaal leesrechten.

Let op:
- `GOOGLE_SEARCH_CONSOLE_SITE_URL` moet exact overeenkomen met de property.
- Voorbeelden:
  - `https://garagebook.nl/`
  - `sc-domain:garagebook.nl`

## Overige env variabelen

Zet lokaal en live ook:

```dotenv
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
