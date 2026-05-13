# Analytics app

GarageBook gebruikt GA4 voor app-events op `app.garagebook.nl` via een sessie-gebaseerde event queue. De backend zet na een succesvolle actie een event in de sessie, waarna de frontend helper `window.garagebookTrack()` dat event op de eerstvolgende pagina-load naar `gtag('event', ...)` verstuurt.

## Laadstrategie

- Centrale config: [config/analytics.php](/home/willem/garagebook/config/analytics.php)
- Runtime helper: [app/Support/Analytics.php](/home/willem/garagebook/app/Support/Analytics.php)
- Google tag partial: [resources/views/partials/google-tag.blade.php](/home/willem/garagebook/resources/views/partials/google-tag.blade.php)
- Event replay partial: [resources/views/partials/analytics-tracking.blade.php](/home/willem/garagebook/resources/views/partials/analytics-tracking.blade.php)

Measurement ID:
- `G-6KJM1W5N63` als default via `config('analytics.ga4.measurement_id')`
- Overschrijfbaar via `GA4_MEASUREMENT_ID`

Production-only gedrag:
- Tracking is alleen actief wanneer `app()->environment('production')` waar is.
- Geen GA4 output in `local`, `testing` of `staging`.

Cross-domain:
- GA4 linker domains zijn geconfigureerd voor `garagebook.nl` en `app.garagebook.nl`.
- `/start` behoudt querystrings zodat UTM-parameters van marketingverkeer niet verloren gaan richting `/admin/register`.

## Event overzicht

- `sign_up`
  - Betekenis: conversie, registratie succesvol afgerond
  - Trigger: [app/Filament/Auth/Register.php](/home/willem/garagebook/app/Filament/Auth/Register.php:57)
  - App section: `auth`

- `login`
  - Betekenis: gebruiker succesvol ingelogd
  - Trigger: [app/Listeners/TrackSuccessfulLogin.php](/home/willem/garagebook/app/Listeners/TrackSuccessfulLogin.php:25)
  - App section: `auth`

- `vehicle_created`
  - Trigger: [app/Filament/Resources/Vehicles/Pages/CreateVehicle.php](/home/willem/garagebook/app/Filament/Resources/Vehicles/Pages/CreateVehicle.php:31)
  - App section: `vehicles`

- `maintenance_log_created`
  - Trigger: [app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php](/home/willem/garagebook/app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php:31)
  - App section: `maintenance`

- `fuel_entry_created`
  - Trigger: [app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php](/home/willem/garagebook/app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php:31)
  - App section: `fuel`

- `document_uploaded`
  - Trigger: [app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php](/home/willem/garagebook/app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php:23)
  - App section: `documents`

- `trip_log_created`
  - Trigger: [app/Filament/Resources/TripLogs/Pages/CreateTripLog.php](/home/willem/garagebook/app/Filament/Resources/TripLogs/Pages/CreateTripLog.php:35)
  - App section: `trips`

Belangrijk:
- Een registratieflow kan bewust zowel `sign_up` als `login` sturen.
- `sign_up` meet de voltooide registratie.
- `login` meet de succesvolle ingelogde sessie.
- Dat beide events in dezelfde flow voorkomen is dus verwacht gedrag.

## Standaardparameters

Minimaal waar logisch meegestuurd:

- `user_id`
  - Alleen als beschikbaar
- `vehicle_id`
  - Alleen als beschikbaar
- `page_path`
  - Frontend runtime context via `window.location.pathname`
- `hostname`
  - Frontend runtime context via `window.location.hostname`
- `app_section`
  - Bepaald per eventtype

Aanvullende niet-privacygevoelige parameters:

- `method`
- `document_type`
- `vehicle_type`
- `has_cost`
- `has_attachment`
- `source`

Niet versturen:

- kentekens
- documentnamen
- beschrijvingen
- notities
- e-mailadressen
- VINs
- onderhoudsteksten

## Triggerlocaties

- Sessietracker: [app/Support/AnalyticsEventTracker.php](/home/willem/garagebook/app/Support/AnalyticsEventTracker.php)
- Login listener: [app/Listeners/TrackSuccessfulLogin.php](/home/willem/garagebook/app/Listeners/TrackSuccessfulLogin.php)
- Register flow: [app/Filament/Auth/Register.php](/home/willem/garagebook/app/Filament/Auth/Register.php)
- Vehicle create: [app/Filament/Resources/Vehicles/Pages/CreateVehicle.php](/home/willem/garagebook/app/Filament/Resources/Vehicles/Pages/CreateVehicle.php)
- Maintenance create: [app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php](/home/willem/garagebook/app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php)
- Fuel create: [app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php](/home/willem/garagebook/app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php)
- Document create: [app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php](/home/willem/garagebook/app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php)
- Trip create: [app/Filament/Resources/TripLogs/Pages/CreateTripLog.php](/home/willem/garagebook/app/Filament/Resources/TripLogs/Pages/CreateTripLog.php)
- UTM redirect behoud: [routes/web.php](/home/willem/garagebook/routes/web.php)

## Voorbeeld payloads

`sign_up`

```json
{
  "user_id": 123,
  "page_path": "/admin",
  "hostname": "app.garagebook.nl",
  "app_section": "auth",
  "method": "email"
}
```

`vehicle_created`

```json
{
  "user_id": 123,
  "vehicle_id": 456,
  "page_path": "/admin/vehicles",
  "hostname": "app.garagebook.nl",
  "app_section": "vehicles",
  "source": "app"
}
```

`trip_log_created`

```json
{
  "user_id": 123,
  "vehicle_id": 456,
  "page_path": "/admin/trip-logs",
  "hostname": "app.garagebook.nl",
  "app_section": "trips",
  "source": "app"
}
```

## Testen in GA4 DebugView

1. Zet de productie-Measurement ID actief in production.
2. Open de app met een browser waarin GA4 DebugView zichtbaar is, bijvoorbeeld via GA Debugger of GTM/GA debug extensie.
3. Voer een testactie uit:
   - registreren
   - inloggen
   - voertuig aanmaken
   - onderhoudslog aanmaken
   - brandstofregistratie aanmaken
   - document uploaden
   - ritregistratie aanmaken
4. Controleer in GA4 DebugView dat:
   - het event exact de juiste eventnaam heeft
   - `user_id`, `vehicle_id`, `page_path`, `hostname` en `app_section` aanwezig zijn waar logisch
   - geen privacygevoelige velden in de payload staan

## Key Events in GA4

Om events als conversie te gebruiken:

1. Open GA4 Admin.
2. Ga naar `Data display` of `Events` afhankelijk van de GA4 interface.
3. Zoek het event, bijvoorbeeld `sign_up`.
4. Markeer het event als `Key event`.

Aanbevolen eerste Key Event:

- `sign_up`

Optioneel afhankelijk van productdoelen:

- `vehicle_created`
- `maintenance_log_created`
- `trip_log_created`
