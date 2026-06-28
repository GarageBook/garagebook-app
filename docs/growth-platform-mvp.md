# GarageBook Growth Platform MVP Backlog

> Implementatiebacklog voor de eerste interne versie van het GarageBook Growth Platform. Gebaseerd op `docs/outreach-2026.md`, `docs/prospects/` en `docs/campaigns/`.

## Scope

De MVP bouwt geen CRM, geen affiliateplatform en geen extern partnerportaal. De MVP levert interne tooling om de growth-funnel meetbaar te maken en de eerste partner- en campagne-experimenten gecontroleerd uit te voeren.

De implementatie richt zich op vijf outcomes:

1. Registratiebronnen betrouwbaar vastleggen.
2. Activatie per bron en campagne kunnen meten.
3. Campagnes intern kunnen beheren.
4. Prospects/partners minimaal kunnen opvolgen.
5. Geratel, Club2026 en Classic2026 als eerste meetbare growth-experimenten kunnen draaien.

## Feature 1: Growth-attributie uitbreiden

### Doel

Leg per registratie consistente growth-attributie vast: source, campaign, partner, UTM-parameters en landing page.

### Waarom nu

Zonder betrouwbare attributie zijn campagnes, partners en activatiekwaliteit niet te beoordelen. Dit is de basis voor alle volgende features.

### Afhankelijkheden

- Bestaande registratieflow.
- Bestaande `UserAttribution` of vergelijkbare attributielogica.
- Bestaande `/start`, `/register` en `/admin/register/geratel` flows.

### Benodigde databasewijzigingen

- Uitbreiden van bestaande user-attributietabel of toevoegen van velden:
  - `source`
  - `campaign_slug`
  - `partner_slug`
  - `utm_source`
  - `utm_medium`
  - `utm_campaign`
  - `utm_content`
  - `landing_page`
- Geen wijziging aan `users.registration_source` behalve blijven gebruiken.

### Benodigde Filament Resources

- Geen nieuwe resource nodig.
- Eventueel read-only weergave op User detail of growth dashboard.

### Benodigde modellen

- Bestaand `UserAttribution` model uitbreiden of formaliseren.
- Geen nieuw model als bestaande attributie afdoende is.

### Dashboardimpact

- Growth dashboard kan registraties groeperen op `registration_source`, `source`, `campaign_slug` en `partner_slug`.
- Eerste basis voor acquisition en activation reporting.

### Acceptatiecriteria

- UTM-parameters blijven behouden van landing tot registratie.
- Geratel-registraties blijven `registration_source = geratel` krijgen.
- Een registratie via `/start?utm_source=x&utm_medium=partner&utm_campaign=club2026` slaat de UTM-data op.
- Attributie is na registratie in de database beschikbaar en niet alleen in de sessie.
- Bestaande reguliere registratie blijft werken zonder attributievelden.

### Verwachte implementatiegrootte

M

## Feature 2: MailerLite growth fields uitbreiden

### Doel

Stuur relevante growth-herkomst als MailerLite subscriber fields mee, zonder extra verplichte MailerLite groups.

### Waarom nu

Geratel is de eerste concrete partner/source. MailerLite moet segmentatie kunnen ondersteunen, maar GarageBook blijft de bron van waarheid.

### Afhankelijkheden

- Feature 1 voor consistente attributie.
- Bestaande MailerLite job/client.
- Custom fields in MailerLite: minimaal `registration_source`; later `source`, `campaign`, `partner_slug`.

### Benodigde databasewijzigingen

- Geen extra databasewijzigingen als Feature 1 de velden opslaat.

### Benodigde Filament Resources

- Geen.

### Benodigde modellen

- Geen nieuw model.
- Bestaande MailerLite job moet fields kunnen ontvangen.

### Dashboardimpact

- Geen directe UI-impact.
- Indirect betere segmentatie in MailerLite.

### Acceptatiecriteria

- Normale registratie blijft naar de default MailerLite group gaan.
- Geratel-registratie stuurt `registration_source = geratel` als field mee.
- Ontbrekende optionele fields breken registratie niet.
- Bestaande subscriber-upsert blijft werken.
- MailerLite failures blijven zichtbaar via queue/failed jobs/logs.

### Verwachte implementatiegrootte

S

## Feature 3: Activation metrics per bron

### Doel

Maak zichtbaar welke registratiebronnen gebruikers opleveren die daadwerkelijk activeren.

### Waarom nu

Registraties alleen zijn te oppervlakkig. GarageBook moet kunnen zien welke bronnen gebruikers opleveren die een voertuig en onderhoudslog toevoegen.

### Afhankelijkheden

- Feature 1.
- Bestaande relaties tussen users, vehicles en maintenance logs.
- Bestaande login tracking indien beschikbaar.

### Benodigde databasewijzigingen

- Bij voorkeur geen nieuwe velden als bestaande timestamps volstaan.
- Optioneel later:
  - `users.first_vehicle_created_at`
  - `users.first_maintenance_log_created_at`
- Voor MVP kan dit via queries op bestaande tabellen.

### Benodigde Filament Resources

- Geen nieuwe resource.
- Uitbreiding van bestaand growth/analytics dashboard.

### Benodigde modellen

- Geen nieuw model.
- Mogelijk serviceklasse voor activation metrics.

### Dashboardimpact

Nieuwe activation cards/tabel:

- Registraties per bron.
- Gebruikers met voertuig per bron.
- Gebruikers met onderhoudslog per bron.
- Activatiepercentage per bron.
- Geratel versus normale registratie.

### Acceptatiecriteria

- Dashboard toont per `registration_source` aantal registraties en activatiepercentage.
- Dashboard toont per `utm_campaign` activatie waar data beschikbaar is.
- Gebruikers zonder voertuig zijn telbaar.
- Gebruikers met voertuig maar zonder onderhoudslog zijn telbaar.
- Queries blijven performant op huidige dataset.

### Verwachte implementatiegrootte

M

## Feature 4: Campaign model

### Doel

Leg growth-campagnes centraal vast zodat Club2026, Classic2026, Event2026, Training2026, Workshop2026 en Media2026 meetbaar en beheerbaar worden.

### Waarom nu

Campagnes bestaan nu als documentatie. Om registraties, partners en activatie te koppelen is een minimale campagne-entiteit nodig.

### Afhankelijkheden

- Feature 1.
- Campagnedocumentatie in `docs/campaigns/`.

### Benodigde databasewijzigingen

Nieuwe tabel `growth_campaigns`:

- `id`
- `name`
- `slug` unique
- `type` enum/string: acquisition, activation, retention, referral, partner
- `funnel_stage` string of JSON
- `status` enum/string: draft, active, paused, completed, archived
- `target_audience`
- `proposition`
- `primary_cta`
- `landing_route`
- `default_utm_source`
- `default_utm_medium`
- `default_utm_campaign`
- `success_metric`
- `starts_at`
- `ends_at`
- `owner_id` nullable foreign key to users
- timestamps

### Benodigde Filament Resources

- `GrowthCampaignResource`
  - List met status, type, funnel stage en slug.
  - Create/edit voor MVP-velden.
  - Filter op status/type.

### Benodigde modellen

- `GrowthCampaign`
- Relatie naar owner `User` optioneel.

### Dashboardimpact

- Campaign performance kan later aan campaign records gekoppeld worden.
- Dashboard kan actieve campagnes tonen.

### Acceptatiecriteria

- Admin kan campagne aanmaken, wijzigen, pauzeren en archiveren.
- Slug is uniek en wordt gebruikt voor UTM-conventie.
- Eerste campagnes kunnen worden ingevoerd: `club2026`, `classic2026`, `event2026`, `training2026`, `workshop2026`, `media2026`.
- Geen publieke routes nodig.

### Verwachte implementatiegrootte

M

## Feature 5: Campaign performance dashboard

### Doel

Toon per campagne of deze registraties en activatie oplevert.

### Waarom nu

Campagnes zonder performance zijn alleen administratie. De MVP moet kunnen bepalen of een campagne moet stoppen of opschalen.

### Afhankelijkheden

- Feature 1.
- Feature 3.
- Feature 4.

### Benodigde databasewijzigingen

- Geen extra wijzigingen als campaign slug in attributie beschikbaar is.

### Benodigde Filament Resources

- Uitbreiding op `GrowthCampaignResource` met view of relation-like stats.
- Dashboard widget voor campaign performance.

### Benodigde modellen

- Geen nieuw model.
- Mogelijk `GrowthDashboardData` of nieuwe service `GrowthCampaignMetrics`.

### Dashboardimpact

Nieuwe tabel/widget:

- Campagne.
- Status.
- Registraties.
- Gebruikers met voertuig.
- Gebruikers met onderhoudslog.
- Activatiepercentage.
- Laatste registratie.

### Acceptatiecriteria

- Active campaigns zijn zichtbaar met performance metrics.
- Campagnes zonder registraties worden zichtbaar.
- Geratel/training2026 kan apart worden beoordeeld.
- Metrics zijn exporteerbaar of minimaal scanbaar in admin.

### Verwachte implementatiegrootte

M

## Feature 6: Prospect model voor partnerkanalen

### Doel

Beheer potentiële partners intern met minimale velden voor segment, prioriteit, status, opvolging en campagnekoppeling.

### Waarom nu

De prospectlijsten in `docs/prospects/` zijn nuttig als start, maar opvolging vraagt om status, follow-updatum en koppeling aan campagnes.

### Afhankelijkheden

- Feature 4.
- Prospectdocumentatie in `docs/prospects/`.

### Benodigde databasewijzigingen

Nieuwe tabel `growth_prospects`:

- `id`
- `name`
- `website`
- `category`
- `subcategory`
- `region`
- `estimated_reach` nullable string
- `newsletter_status` enum/string: yes, no, unknown
- `primary_contact_channel`
- `contact_name`
- `email`
- `organizes_events` enum/string: yes, no, unknown
- `has_magazine` enum/string: yes, no, unknown
- `has_facebook` enum/string: yes, no, unknown
- `has_instagram` enum/string: yes, no, unknown
- `priority` enum/string: A, B, C
- `warmth` enum/string: warm, lukewarm, cold
- `score` tiny integer nullable
- `status` enum/string
- `campaign_id` nullable foreign key
- `partner_slug` nullable unique
- `notes` text nullable
- `why_interesting` text nullable
- `approach_strategy` text nullable
- `last_contacted_at` nullable datetime
- `next_follow_up_at` nullable datetime
- timestamps

### Benodigde Filament Resources

- `GrowthProspectResource`
  - List met naam, categorie, prioriteit, warmte, score, campagne, status, follow-up.
  - Filters: campagne, status, prioriteit, warmte, categorie.
  - Create/edit voor alle MVP-velden.

### Benodigde modellen

- `GrowthProspect`
- BelongsTo `GrowthCampaign`

### Dashboardimpact

- Partner dashboard kan prospects per status tonen.
- Follow-up widgets kunnen op `next_follow_up_at` draaien.

### Acceptatiecriteria

- Admin kan motorclubprospects handmatig invoeren.
- Prospect kan aan campagne worden gekoppeld.
- Status kan worden bijgewerkt naar contacted, interested, active, archived.
- Prioriteit/warmte/score zijn filterbaar.
- Geen bulkimport vereist in MVP.

### Verwachte implementatiegrootte

L

## Feature 7: Prospect follow-up dashboard

### Doel

Maak opvolging uitvoerbaar door prospects met vandaag/achterstallige follow-up zichtbaar te maken.

### Waarom nu

Zonder follow-upoverzicht wordt prospectbeheer alsnog een losse spreadsheet. De waarde zit in consistente opvolging.

### Afhankelijkheden

- Feature 6.

### Benodigde databasewijzigingen

- Geen extra wijzigingen bovenop `growth_prospects.next_follow_up_at`, `last_contacted_at` en `status`.

### Benodigde Filament Resources

- Widget op Growth dashboard of Prospect resource:
  - Follow-ups vandaag.
  - Achterstallige follow-ups.
  - Interested zonder follow-updatum.

### Benodigde modellen

- Geen nieuw model.

### Dashboardimpact

Nieuwe follow-up widget:

- Aantal follow-ups vandaag.
- Aantal achterstallig.
- Top 10 concrete prospects om op te volgen.

### Acceptatiecriteria

- Prospects met `next_follow_up_at <= today` zijn zichtbaar.
- Afgehandelde/archived prospects verdwijnen uit follow-upoverzicht.
- Widget linkt naar prospect edit/detail.
- Geen automatische e-mailverzending.

### Verwachte implementatiegrootte

S

## Feature 8: Partnerlink generator

### Doel

Maak consistente trackinglinks voor prospects, partners en campagnes.

### Waarom nu

Handmatig UTM-links bouwen is foutgevoelig. Consistente links zijn nodig voor betrouwbare attributie.

### Afhankelijkheden

- Feature 1.
- Feature 4.
- Feature 6.

### Benodigde databasewijzigingen

- Geen extra wijzigingen als prospect `partner_slug` en campaign UTM-velden heeft.
- Optioneel veld `tracking_url` op prospect kan gegenereerd worden opgeslagen of alleen berekend worden.

### Benodigde Filament Resources

- Actie op `GrowthProspectResource`: trackinglink kopiëren of tonen.
- Actie op `GrowthCampaignResource`: voorbeeldlink tonen.

### Benodigde modellen

- Geen nieuw model.
- Serviceklasse voor URL-generatie aanbevolen.

### Dashboardimpact

- Partner dashboard kan tonen welke prospects een link hebben.

### Acceptatiecriteria

- Voor prospect + campagne wordt link gegenereerd volgens:
  `/start?utm_source={partner_slug}&utm_medium=partner&utm_campaign={campaign_slug}&utm_content={placement}`.
- Geratel/training kan eigen registerroute blijven gebruiken.
- Link gebruikt veilige slugwaarden.
- Link is kopieerbaar vanuit Filament.

### Verwachte implementatiegrootte

S

## Feature 9: Handmatige prospectimport vanuit documentatie

### Doel

Breng de bestaande motorclubprospects uit `docs/prospects/motorclubs.md` handmatig of semi-handmatig in de database zonder CSV-importsysteem te bouwen.

### Waarom nu

De MVP heeft echte data nodig om bruikbaar te zijn, maar een volledige CSV-import met mapping en dry-run is nog te groot.

### Afhankelijkheden

- Feature 6.
- Feature 4 voor campagnekoppeling.

### Benodigde databasewijzigingen

- Geen extra wijzigingen.

### Benodigde Filament Resources

- `GrowthProspectResource` moet snel handmatig invoeren ondersteunen.
- Eventueel eenvoudige artisan seed/import command later, maar niet vereist voor MVP.

### Benodigde modellen

- Geen nieuw model.

### Dashboardimpact

- Prospect en follow-up dashboards krijgen echte data.

### Acceptatiecriteria

- Minimaal de 30 motorclubprospects kunnen in het systeem worden gezet.
- Alle velden uit de documenttabel hebben een plek of worden bewust genegeerd.
- Onbekende velden blijven unknown/null.
- Geen code voor generieke CSV-import vereist.

### Verwachte implementatiegrootte

XS bij handmatige invoer, M als een eenmalige importcommand wordt gebouwd.

## Feature 10: Growth overview dashboard

### Doel

Bied één intern overzicht van acquisition, activation en partner/opvolging voor de MVP.

### Waarom nu

Losse resources zijn niet genoeg. De gebruiker moet in één scherm zien waar groei stagneert.

### Afhankelijkheden

- Feature 3.
- Feature 5.
- Feature 7.

### Benodigde databasewijzigingen

- Geen extra wijzigingen.

### Benodigde Filament Resources

- Nieuwe of bestaande `GrowthDashboard` uitbreiden.
- Widgets:
  - Registraties per bron.
  - Activatie per bron.
  - Campaign performance.
  - Follow-ups.

### Benodigde modellen

- Geen nieuw model.
- Metrics service aanbevolen.

### Dashboardimpact

Dit is de dashboardimpact: het MVP-commandocentrum voor growth.

### Acceptatiecriteria

- Dashboard toont registraties per bron.
- Dashboard toont activatie per bron.
- Dashboard toont actieve campagnes met performance.
- Dashboard toont follow-ups vandaag/achterstallig.
- Dashboard gebruikt alleen interne data en blijft bruikbaar zonder GA4.

### Verwachte implementatiegrootte

M

## Feature 11: Stop/opschaalvelden per campagne

### Doel

Maak campagnes operationeel stuurbaar door stop- en opschaalcriteria vast te leggen.

### Waarom nu

De campagnedocumenten bevatten duidelijke criteria. Zonder deze criteria in het systeem blijven campagnes te vrijblijvend.

### Afhankelijkheden

- Feature 4.
- Campagnedocumentatie in `docs/campaigns/`.

### Benodigde databasewijzigingen

Uitbreiden `growth_campaigns`:

- `stop_criteria` text nullable
- `scale_criteria` text nullable
- `kpi_notes` text nullable

### Benodigde Filament Resources

- Velden toevoegen aan `GrowthCampaignResource`.
- Tonen op campaign detail/edit.

### Benodigde modellen

- `GrowthCampaign` velden/casts uitbreiden.

### Dashboardimpact

- Campaign performance kan visueel aangeven of een campagne aandacht nodig heeft.

### Acceptatiecriteria

- Per campagne kunnen stopcriteria en opschaalcriteria worden vastgelegd.
- Criteria zijn zichtbaar naast performance metrics.
- Club2026 en Classic2026 bevatten concrete criteria uit de documentatie.

### Verwachte implementatiegrootte

S

## Feature 12: Retention segment signals

### Doel

Maak de eerste retentiesegmenten zichtbaar: geregistreerd zonder voertuig, voertuig zonder onderhoudslog, lang niet ingelogd.

### Waarom nu

Growth stopt niet bij acquisitie. Deze signalen bepalen welke onboarding en lifecycle-opvolging nodig is.

### Afhankelijkheden

- Feature 3.
- Bestaande login/vehicle/maintenance data.

### Benodigde databasewijzigingen

- Geen extra wijzigingen voor MVP.
- Eventueel later denormalized timestamps op `users` voor performance.

### Benodigde Filament Resources

- Widget of tabel op Growth dashboard.
- Mogelijk link naar filtered UserResource indien beschikbaar.

### Benodigde modellen

- Geen nieuw model.

### Dashboardimpact

Nieuwe retention/activation segmenten:

- Nieuwe users zonder voertuig.
- Users met voertuig zonder onderhoudslog.
- Users zonder recente login.

### Acceptatiecriteria

- Segmenten zijn zichtbaar met aantallen.
- Segmenten kunnen per registration_source/campaign worden gefilterd of uitgesplitst.
- Geen automatische e-mails in MVP.

### Verwachte implementatiegrootte

M

## Sprintplanning

### Sprint 1

1. Feature 1: Growth-attributie uitbreiden.
2. Feature 2: MailerLite growth fields uitbreiden.
3. Feature 3: Activation metrics per bron.

Doel van sprint 1: betrouwbare bronmeting en activatie-inzicht, met Geratel als eerste concrete validatie.

### Sprint 2

1. Feature 4: Campaign model.
2. Feature 5: Campaign performance dashboard.
3. Feature 11: Stop/opschaalvelden per campagne.

Doel van sprint 2: campagnes worden echte growth-experimenten met meetbare resultaten.

### Sprint 3

1. Feature 6: Prospect model voor partnerkanalen.
2. Feature 7: Prospect follow-up dashboard.

Doel van sprint 3: partner- en prospectopvolging intern beheerbaar maken zonder CRM-overbouw.

### Sprint 4

1. Feature 8: Partnerlink generator.
2. Feature 9: Handmatige prospectimport vanuit documentatie.
3. Feature 10: Growth overview dashboard.

Doel van sprint 4: de eerste motorclubcampagne operationeel kunnen uitvoeren vanuit Filament.

### Sprint 5

1. Feature 12: Retention segment signals.
2. Verfijnen van dashboards op basis van eerste campagne- en prospectdata.
3. Besluit voorbereiden: CSV-import, templatebeheer of referral measurement als volgende uitbreiding.

Doel van sprint 5: growth-funnel sluiten van acquisitie naar activatie en eerste retentiesignalen.
