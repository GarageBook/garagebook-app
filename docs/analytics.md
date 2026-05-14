# Analytics

GarageBook gebruikt de bestaande sessie-gebaseerde analytics queue voor app-events op `app.garagebook.nl`. De backend zet na een succesvolle actie een privacyveilige event-payload in de sessie, waarna de frontend helper `window.garagebookTrack()` die payload naar `dataLayer.push(...)` of `gtag('event', ...)` doorzet.

## Event overzicht

### `account_registered`
- Trigger: na succesvolle registratie in [app/Filament/Auth/Register.php](/home/willem/garagebook/app/Filament/Auth/Register.php:1)
- Parameters:
  - `method`
  - `user_id_hash`
  - `source_url`
  - `utm_source`
  - `utm_medium`
  - `utm_campaign`
  - `utm_content`
  - `utm_term`
- Privacy:
  - geen emailadres
  - geen naam
  - geen ruwe user ID

### `dashboard_viewed`
- Trigger: bij dashboard-load in [app/Filament/Pages/Dashboard.php](/home/willem/garagebook/app/Filament/Pages/Dashboard.php:1)
- Parameters:
  - `vehicle_count`
  - `maintenance_log_count`
  - `document_count`
  - `fuel_log_count`
- Privacy:
  - alleen geaggregeerde aantallen

### `vehicle_created`
- Trigger: na succesvol voertuig aanmaken in [app/Filament/Resources/Vehicles/Pages/CreateVehicle.php](/home/willem/garagebook/app/Filament/Resources/Vehicles/Pages/CreateVehicle.php:1)
- Parameters:
  - `vehicle_type`
  - `is_first_vehicle`
  - `vehicle_count_after_create`
- Privacy:
  - geen kenteken
  - geen vrije tekstvelden

### `maintenance_log_created`
- Trigger: na succesvol onderhoudslog aanmaken in [app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php](/home/willem/garagebook/app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php:1)
- Parameters:
  - `is_first_maintenance_log`
  - `vehicle_id_hash`
  - `has_attachments`
  - `cost_entered`
- Privacy:
  - geen beschrijving
  - geen notities
  - geen ruwe vehicle ID

### `document_uploaded`
- Trigger: na succesvol document toevoegen in [app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php](/home/willem/garagebook/app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php:1)
- Parameters:
  - `document_type`
  - `vehicle_id_hash`
  - `file_count`
- Privacy:
  - geen bestandsnamen
  - geen vrije tekstvelden

### `fuel_log_created`
- Trigger: na succesvol brandstoflog aanmaken in [app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php](/home/willem/garagebook/app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php:1)
- Parameters:
  - `unit`
  - `calculated_consumption_available`
  - `vehicle_id_hash`
- Privacy:
  - geen locatie- of vrije tekstvelden
  - geen ruwe vehicle ID

### `public_share_created`
- Trigger: bij bestaande share/export-acties in [app/Filament/Resources/MaintenanceLogs/Pages/ListMaintenanceLogs.php](/home/willem/garagebook/app/Filament/Resources/MaintenanceLogs/Pages/ListMaintenanceLogs.php:1)
- Parameters:
  - `vehicle_id_hash`
  - `source`
- Privacy:
  - geen publiek deel-URL
  - geen ruwe vehicle ID

### `app_cta_clicked`
- Trigger: bij bestaande onboarding/empty-state CTA’s, zoals:
  - eerste voertuig toevoegen
  - onderhoudslog toevoegen
  - document uploaden
  - brandstoflog toevoegen
- Parameters:
  - `cta_name`
  - `location`
  - `user_state`
- Privacy:
  - geen gebruikersnamen
  - geen vrije tekstvelden

## Frontend gedrag

- Centrale runtime helper: [resources/views/partials/analytics-tracking.blade.php](/home/willem/garagebook/resources/views/partials/analytics-tracking.blade.php:1)
- Kliktracking gebruikt data-attributen die door [app/Support/Analytics.php](/home/willem/garagebook/app/Support/Analytics.php:1) worden opgebouwd.
- Als GTM aanwezig is, gebruikt GarageBook `dataLayer.push(...)`.
- Als alleen `gtag` aanwezig is, gebruikt GarageBook `gtag('event', ...)`.
- Als analytics wordt geblokkeerd door adblockers of privacytools, faalt de helper stil zonder JavaScript errors.
- In `local` logt de helper events naar `console.info(...)` en bewaart hij ze ook in `window.dataLayer` en `window.garagebookAnalyticsEvents` voor handmatige controle.

## First-touch attributie

- Capture middleware: [app/Http/Middleware/CaptureAnalyticsAttribution.php](/home/willem/garagebook/app/Http/Middleware/CaptureAnalyticsAttribution.php:1)
- Sessiesleutel: [app/Support/AnalyticsAttribution.php](/home/willem/garagebook/app/Support/AnalyticsAttribution.php:1)
- Persistente opslag: [app/Models/UserAttribution.php](/home/willem/garagebook/app/Models/UserAttribution.php:1)

Vastgelegd waar beschikbaar:
- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_content`
- `utm_term`
- `landing_page`
- `referrer`

Gedrag:
- alleen first-touch
- geen overschrijven van bestaande sessie-attributie
- alleen opslaan als er UTM’s of een externe referrer beschikbaar zijn
- koppeling aan de nieuwe user direct na succesvolle registratie

## Privacyregels

Niet naar GA4 sturen:
- emailadressen
- namen
- kentekens
- chassisnummers of VINs
- bestandsnamen
- vrije tekstvelden
- ruwe `user_id`
- ruwe `vehicle_id`

Wel toegestaan:
- eventnamen
- gehashte identifiers
- booleans
- tellingen
- generieke types zoals `document_type` of `vehicle_type`

## Lokaal testen

1. Open lokaal een pagina met CTA of create-flow.
2. Controleer in de browserconsole `window.garagebookAnalyticsEvents`.
3. Controleer `window.dataLayer` op de laatste gepushte events.
4. Voer flows uit zoals:
   - registratie
   - dashboard openen
   - voertuig aanmaken
   - onderhoudslog aanmaken
   - document uploaden
   - brandstoflog aanmaken
   - share/export klikken
5. Controleer dat de payload geen PII bevat.

## GA4 DebugView

1. Zet de productie-Measurement ID actief op live.
2. Open GA4 DebugView.
3. Activeer de gewenste flow op live nadat code is gedeployed.
4. Controleer per event:
   - exacte eventnaam
   - verwachte parameters
   - geen PII
