# Doel

Implementeer een eerste generieke Lifecycle E-mail feature voor GarageBook, gebaseerd op de bestaande lifecycle/outreach-achtige mail-, queue-, logging- en Filament-patronen. De eerste concrete trigger is `no_vehicle_day2`: geverifieerde gebruikers die langer dan 2 dagen een account hebben, maar nog geen voertuig hebben toegevoegd, krijgen een lifecycle-mail met een CTA naar het voertuig-aanmaakformulier.

# Wat is gerealiseerd

- De bestaande lifecycle-module is compatibel uitgebreid in plaats van een parallel systeem te introduceren.
- `lifecycle_email_logs` is uitgebreid met velden die de gevraagde generieke lifecycle-logstructuur ondersteunen: `trigger`, `mail_class`, `queued_at` en `error`.
- Een nieuwe lifecycle-triggerconstante `no_vehicle_day2` is toegevoegd aan `LifecycleEmailLog`.
- Een nieuwe mail `NoVehicleDay2Mail` is toegevoegd.
- Een nieuwe Blade-template voor de mail is toegevoegd en gebruikt de bestaande lifecycle-mailkaart partial.
- Een nieuwe service `App\Services\Lifecycle\LifecycleEmailService` is toegevoegd met `queueNoVehicleUsers()`.
- De bestaande `SendLifecycleEmailJob` is uitgebreid om `no_vehicle_day2` logs af te handelen.
- De job controleert direct voor verzending opnieuw of de gebruiker nog bestaat en nog steeds geen voertuig heeft.
- Als de gebruiker inmiddels een voertuig heeft, wordt de log `skipped` met `vehicle_added` en wordt er geen mail verstuurd.
- Bij succesvolle verzending wordt de log `sent` en wordt `sent_at` gevuld.
- Bij uitzonderingen wordt de log `failed` en wordt de fout opgeslagen in zowel `error` als `error_message`.
- Een nieuw artisan command is toegevoegd: `garagebook:lifecycle:no-vehicle`.
- De bestaande Filament resource `Lifecycle Email Logs` is uitgebreid met extra kolommen voor de nieuwe generieke velden.
- Er zijn featuretests toegevoegd voor queueing, idempotentie, skip bij tussentijds voertuig en succesvolle verzending.
- Command auto-discovery is gecontroleerd via `php artisan list garagebook:lifecycle`.
- De volledige test-suite is succesvol gedraaid.

# Gewijzigde bestanden

Lifecycle-feature bestanden:

- `database/migrations/2026_06_23_213000_add_day2_trigger_fields_to_lifecycle_email_logs_table.php`
- `app/Models/LifecycleEmailLog.php`
- `app/Mail/Lifecycle/NoVehicleDay2Mail.php`
- `resources/views/emails/lifecycle/no-vehicle-day2.blade.php`
- `app/Services/Lifecycle/LifecycleEmailService.php`
- `app/Jobs/SendLifecycleEmailJob.php`
- `app/Console/Commands/QueueNoVehicleLifecycleEmailsCommand.php`
- `app/Filament/Resources/LifecycleEmailLogs/LifecycleEmailLogResource.php`
- `tests/Feature/LifecycleNoVehicleDay2Test.php`

Niet gerelateerd en al eerder geparkeerd in de worktree, niet onderdeel van deze lifecycle-taak:

- `app/Http/Controllers/PublicGarageController.php`
- `routes/web.php`
- `tests/Feature/PublicGaragePageTest.php`
- `app/Console/Commands/SeedPublicGarageV2DemoCommand.php`
- `resources/views/garage/show-v2.blade.php`

# Database wijzigingen

Er is geen nieuwe tabel aangemaakt omdat `lifecycle_email_logs` al bestond in het project.

Nieuwe migratie:

- `database/migrations/2026_06_23_213000_add_day2_trigger_fields_to_lifecycle_email_logs_table.php`

Deze migratie breidt `lifecycle_email_logs` uit met:

- `trigger` nullable string, met index `lifecycle_email_logs_trigger_index`
- `mail_class` nullable string
- `queued_at` nullable timestamp, met index `lifecycle_email_logs_queued_at_index`
- `error` nullable text

Bestaande relevante kolommen blijven behouden, waaronder:

- `user_id`
- `email_key`
- `subject`
- `status`
- `sent_at`
- `failed_at`
- `error_message`
- `skipped_at`
- `reason_skipped`
- bestaande statistiek- en trackingvelden uit latere lifecycle-migraties

De bestaande unieke constraint `user_id + email_key` blijft leidend om dubbele lifecycle-mails voor dezelfde lifecycle-key te voorkomen.

# Architectuur

De lifecycle-module bestaat uit vier hoofdonderdelen: selectie, logging, verzending en beheer.

1. Selectie

`App\Services\Lifecycle\LifecycleEmailService::queueNoVehicleUsers()` selecteert gebruikers die aan deze voorwaarden voldoen:

- `email_verified_at` is gevuld
- account is ouder dan 2 dagen
- gebruiker is niet uitgeschreven voor lifecycle-mails
- gebruiker heeft geen voertuigen
- gebruiker heeft nog geen log met `trigger` of `email_key` `no_vehicle_day2`

Per geschikte gebruiker maakt de service een `lifecycle_email_logs` record aan met status `queued`, `queued_at`, `mail_class` en triggermetadata. Daarna dispatcht de service `SendLifecycleEmailJob` met `userId`, `emailKey` en `logId`.

2. Logging

`App\Models\LifecycleEmailLog` is de centrale audit trail. Voor de nieuwe trigger worden zowel de bestaande `email_key` als de nieuwe `trigger` gevuld met `no_vehicle_day2`, zodat het bestaande lifecycle-systeem compatibel blijft en nieuwe generieke velden beschikbaar zijn.

3. Verzending

`App\Jobs\SendLifecycleEmailJob` bevat de bestaande lifecycle-verzendlogica en is uitgebreid met een speciale route voor `no_vehicle_day2` logs. De job claimt eerst een queued log door die naar `processing` te zetten. Daarna controleert hij opnieuw:

- bestaat de gebruiker nog?
- heeft de gebruiker inmiddels een voertuig?

Bij een inmiddels toegevoegd voertuig wordt de log `skipped` met `vehicle_added`. Bij een ontbrekende gebruiker wordt de log `skipped` met `user_missing`. Alleen als de gebruiker nog steeds eligible is, wordt `NoVehicleDay2Mail` verstuurd via de bestaande Laravel mail stack.

4. Mail

`App\Mail\Lifecycle\NoVehicleDay2Mail` gebruikt de Blade-view `emails.lifecycle.no-vehicle-day2`. Die view gebruikt de bestaande partial `emails.partials.lifecycle-card`, zodat styling en unsubscribe UX consistent zijn met de bestaande lifecycle-mails. De CTA gaat naar `url('/admin/vehicles/create')`.

5. Beheer

`App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource` blijft read-only en is uitgebreid met zichtbaarheid voor `trigger`, `mail_class`, `queued_at`, `sent_at` en `error`. De resource blijft admin-only via de bestaande autorisatiepatronen.

# Nog openstaande werkzaamheden

- [ ] Beslissen of `garagebook:lifecycle:no-vehicle` in de Laravel scheduler moet worden opgenomen.
- [ ] Productmatig bevestigen of `no_vehicle_day2` naast de bestaande lifecycle-key `no_vehicle_added` gewenst blijft, of dat ze later geconsolideerd moeten worden.
- [ ] Eventueel een dry-run optie toevoegen aan `garagebook:lifecycle:no-vehicle` voordat het command live periodiek draait.
- [ ] Eventueel een test toevoegen voor het artisan command zelf, inclusief output `Gevonden`, `Queued`, `Overgeslagen`.
- [ ] Eventueel mailcopy en onderwerp finaliseren met product/marketing voordat live verzending wordt aangezet.
- [ ] Eventueel lifecycle-mail preview/documentatie toevoegen voor admins.
- [ ] Controleren of bestaande retry-command UX de nieuwe `trigger`, `mail_class`, `queued_at` en `error` velden voldoende toont bij retries.

# Bekende aandachtspunten

- Er bestond al een volwassen lifecycle-module. Daarom is gekozen voor compatibel uitbreiden van de bestaande tabel en job in plaats van het aanmaken van een tweede, losstaand lifecycle-systeem.
- De gevraagde tabel `lifecycle_email_logs` bestond al. De nieuwe migratie voegt alleen ontbrekende generieke kolommen toe.
- Voor dubbele verzending blijft de bestaande unieke constraint op `user_id + email_key` belangrijk. Voor `no_vehicle_day2` wordt `email_key` daarom bewust ook gevuld.
- Er staat al een bestaande lifecycle-key `no_vehicle_added` in het systeem. De nieuwe key `no_vehicle_day2` is toegevoegd zoals gevraagd, maar dit overlapt conceptueel met bestaande onboarding/lifecycle-logica.
- Het command is niet tegen de lokale database uitgevoerd om geen echte lokale lifecycle logs/jobs voor bestaande lokale gebruikers aan te maken.
- De worktree bevat nog geparkeerde V2 publieke voertuigpagina-wijzigingen. Die zijn niet geraakt en niet onderdeel van deze feature.
- Door een sandboxprobleem faalde `apply_patch`/complexe lokale edits tijdelijk met `bwrap: loopback: Failed RTM_NEWADDR`. Enkele lokale edits zijn daarom via escalated shell/python uitgevoerd. Er is niet gepusht of gedeployed.

# Teststatus

Toegevoegde testfile:

- `tests/Feature/LifecycleNoVehicleDay2Test.php`

Deze testfile dekt:

- gebruiker zonder voertuig wordt gequeued
- gebruiker met voertuig wordt niet gequeued
- gebruiker krijgt `no_vehicle_day2` maar één keer
- job slaat verzending over wanneer tussentijds een voertuig is toegevoegd
- job verstuurt mail en zet logstatus naar `sent`

Succesvol gedraaide commands:

- `vendor/bin/phpunit tests/Feature/LifecycleNoVehicleDay2Test.php`
  - Resultaat: 5 tests, 18 assertions, groen.
- `vendor/bin/phpunit tests/Feature/LifecycleNoVehicleDay2Test.php tests/Feature/LifecycleEmailFlowTest.php tests/Feature/RetryLifecycleEmailsCommandTest.php tests/Feature/LifecycleEmailAdminResourcesTest.php tests/Feature/AdminManagementAccessTest.php`
  - Resultaat: 62 tests, 301 assertions, groen.
- `php artisan test`
  - Resultaat: 422 tests, 1954 assertions, groen.
- `vendor/bin/pint` gericht op lifecycle PHP-bestanden
  - Resultaat: pass.
- `git diff --check`
  - Resultaat: schoon.

# Handige artisan commands

Commandregistratie controleren:

```bash
php artisan list garagebook:lifecycle
```

No-vehicle lifecycle-mails queueen:

```bash
php artisan garagebook:lifecycle:no-vehicle
```

Migraties lokaal draaien:

```bash
php artisan migrate
```

Gerichte lifecycle-tests draaien:

```bash
vendor/bin/phpunit tests/Feature/LifecycleNoVehicleDay2Test.php tests/Feature/LifecycleEmailFlowTest.php tests/Feature/RetryLifecycleEmailsCommandTest.php tests/Feature/LifecycleEmailAdminResourcesTest.php tests/Feature/AdminManagementAccessTest.php
```

Volledige suite draaien:

```bash
php artisan test
```

Formatter draaien voor de lifecycle-bestanden:

```bash
vendor/bin/pint app/Console/Commands/QueueNoVehicleLifecycleEmailsCommand.php app/Filament/Resources/LifecycleEmailLogs/LifecycleEmailLogResource.php app/Jobs/SendLifecycleEmailJob.php app/Mail/Lifecycle/NoVehicleDay2Mail.php app/Models/LifecycleEmailLog.php app/Services/Lifecycle/LifecycleEmailService.php database/migrations/2026_06_23_213000_add_day2_trigger_fields_to_lifecycle_email_logs_table.php tests/Feature/LifecycleNoVehicleDay2Test.php
```

Whitespace/diff-check:

```bash
git diff --check
```

# Vervolgstappen

De volgende Codex-sessie moet eerst de worktree scheiden: de lifecycle-featurebestanden beoordelen los van de eerder geparkeerde V2 publieke voertuigpagina-wijzigingen. Daarna is de meest logische volgende stap om een kleine command-test voor `garagebook:lifecycle:no-vehicle` toe te voegen en productmatig te beslissen of dit command in de scheduler moet. Als scheduling gewenst is, voeg dan een veilige schedule entry toe en draai opnieuw minimaal de lifecycle-tests plus `AdminManagementAccessTest`, omdat Filament/admin lifecycle resources onderdeel van deze wijziging zijn.
