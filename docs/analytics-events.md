# Analytics events

GarageBook gebruikt een sessie-gebaseerde event queue voor GA4 events in de app. De backend zet na een succesvolle actie een generieke payload in de sessie, waarna een kleine frontend helper `window.garagebookTrack(eventName, params = {})` die payload op de volgende pagina-load naar `gtag('event', ...)` stuurt als `window.gtag` beschikbaar is.

## Gemeten events

- `sign_up`
  - Trigger: [app/Filament/Auth/Register.php](/home/willem/garagebook/app/Filament/Auth/Register.php:58)
  - Params: `method`

- `login`
  - Trigger: [app/Listeners/TrackSuccessfulLogin.php](/home/willem/garagebook/app/Listeners/TrackSuccessfulLogin.php:25)
  - Params: `method`

- `vehicle_created`
  - Trigger: [app/Filament/Resources/Vehicles/Pages/CreateVehicle.php](/home/willem/garagebook/app/Filament/Resources/Vehicles/Pages/CreateVehicle.php:31)
  - Params: `vehicle_type` indien beschikbaar, `source`

- `maintenance_log_created`
  - Trigger: [app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php](/home/willem/garagebook/app/Filament/Resources/MaintenanceLogs/Pages/CreateMaintenanceLog.php:31)
  - Params: `vehicle_type` indien beschikbaar, `has_cost`, `has_attachment`, `source`

- `fuel_entry_created`
  - Trigger: [app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php](/home/willem/garagebook/app/Filament/Resources/FuelLogs/Pages/CreateFuelLog.php:31)
  - Params: `vehicle_type` indien beschikbaar, `source`

- `document_uploaded`
  - Trigger: [app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php](/home/willem/garagebook/app/Filament/Resources/VehicleDocuments/Pages/CreateVehicleDocument.php:23)
  - Params: `document_type` indien beschikbaar, `source`

## Privacy

Deze tracking stuurt geen persoonsgegevens naar GA4. Namen, e-mailadressen, kentekens, VINs, documentnamen, beschrijvingen, notities en onderhoudsteksten blijven buiten de analytics payloads.
