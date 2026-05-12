# Trip Logging

## Fase 1
- ondersteund formaat: `GPX`
- upload per voertuig
- origineel uploadbestand blijft bewaard
- interne route-opslagstandaard: `GeoJSON`
- kaartstack: `Leaflet` + configureerbare tile URL
- verwerking via queue job
- productie heeft een queue worker nodig voor echte achtergrondverwerking

## Privacy en scoping
- elke trip hoort bij een voertuig en gebruiker
- Filament queries scopen altijd via `vehicle.user_id = auth()->id()`
- zo is er geen zichtbaarheid van trips tussen gebruikers

## Technische verwerking
1. gebruiker uploadt een GPX-bestand
2. `trip_logs` record wordt aangemaakt met status `pending`
3. `ProcessTripLogUpload` queue job parseert de route
4. GPX trackpunten worden omgezet naar GeoJSON `LineString`
5. afstand, duur, bounds, timestamps en puntenaantal worden opgeslagen
6. `simplified_geojson` is in de MVP gelijk aan `geojson`

## Queue en productie
- huidige queue-connection in deze app: `database`
- echte asynchrone verwerking werkt pas als er op productie een worker draait
- voorbeeld worker-commando voor Forge of supervisor:
  `php artisan queue:work --tries=3 --timeout=120`
- als `QUEUE_CONNECTION=sync` wordt gebruikt, wordt een trip direct in het request verwerkt en is uploadverwerking dus niet echt async
- web requests en queue workers moeten toegang hebben tot dezelfde uploadbestanden op de gekozen disk
- als uploads door user A worden geschreven en de worker als user B draait, kunnen private directory-permissies de GPX-bron onleesbaar maken
- Trip uploads horen daarom op `storage/app/private/...` te landen, niet in een release directory zoals `public/...` of project-root `temp/...`
- Forge moet tijdens deploy de gedeelde storage-map blijven linken, zodat webverkeer en queue workers dezelfde bestanden zien

## Forge worker-instellingen
- maak in Forge een queue worker aan
- command: `php artisan queue:work --tries=3 --timeout=120`
- directory: `/home/forge/app.garagebook.nl/current`
- user: `forge`
- voeg na deploy een queue-restart toe:
  `php artisan queue:restart`
- draai de queue worker als een user die ook leesrechten heeft op de gedeelde Laravel storage
- voorkom dat web- of queueprocessen tijdelijke bestanden onder `current/public/...` of een release-local `temp/...` aanmaken

## Uploadlimieten
- de Trip uploadvelden in Filament/Livewire staan nu op maximaal `25 MB` via `maxSize(25600)`
- lokale PHP-limieten in deze omgeving zijn nu:
  - `upload_max_filesize=2M`
  - `post_max_size=8M`
- gewenste live-instelling voor Trip uploads:
  - `upload_max_filesize=25M` of hoger
  - `post_max_size=25M` of hoger, praktisch liever `30M`
- als PHP lager staat dan de Filament-limiet, faalt upload al voordat de Trip parser of queue-job start
- parser-guard:
  - GPX-bestanden groter dan `25 MB` worden ook tijdens queue-verwerking afgewezen om onnodig geheugenverbruik te begrenzen

## Forge deploy failure: oude release met tempbestanden
- een Forge deploy kan falen bij `Purging old releases` als een oude release tijdelijke bestanden bevat die niet door de Forge deploy user verwijderd mogen worden
- voorbeeldpad:
  - `public/temp-downloads/...`
  - `temp/willem-import/...`
- in de getrackte GarageBook app-code is geen vast codepad gevonden dat `public/temp-downloads` of project-root `temp/willem-import` aanmaakt
- dit wijst waarschijnlijk op handmatige of ad-hoc export/importbestanden op de server, of bestanden die buiten de normale Laravel storage zijn neergezet
- `.gitignore` helpt alleen om dit lokaal niet mee te nemen; het lost een bestaande live ownership-fout niet op

## Veilige live cleanup-instructie
Alleen handmatig op de server uitvoeren, niet vanuit Laravel-code:

```bash
cd /home/forge/app.garagebook.nl/releases
ls -la 69002725/public/temp-downloads
ls -la 69002725/temp/willem-import
sudo chown -R forge:forge 69002725/public/temp-downloads 69002725/temp
```

Daarna deploy opnieuw proberen.

Als je zeker weet dat dit alleen tijdelijke export/importbestanden zijn:

```bash
cd /home/forge/app.garagebook.nl/releases
sudo rm -rf 69002725/public/temp-downloads 69002725/temp
```

Gebruik dit alleen als de inhoud niet persistent hoeft te blijven.

## Toekomstige formaten
- fase 2: `TCX`, `KML`, `KMZ`, `GeoJSON`
- fase 3: `FIT`, Douglas-Peucker simplificatie, GPX export
- fase 4: publieke trip shares, foto-koppeling, trip collections
