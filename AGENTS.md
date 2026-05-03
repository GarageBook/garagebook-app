# Repository Guidelines

## Project Context
GarageBook is a Laravel 13 + Filament 5 project. The codebase is moving from MVP to a more mature product over the next 3 months, so changes must favor stability, clarity, and incremental improvement over speed.

## Working Method
Work safely and in small steps. First analyze the existing code and architecture, then propose the change, and only implement after explicit approval. Do not make speculative refactors. If a request exposes hidden complexity, surface it before editing.

## Critical Areas
Treat these areas as high-risk and validate them carefully before and after changes:
- authorization and panel access
- tests and regression coverage
- CSS consistency between public app and Filament admin
- domain logic around vehicles, maintenance logs, reminders, sharing, and exports

## Code Quality Expectations
Explicitly flag duplicate, legacy, or dead code when you find it. In this repository that includes overlapping resources, unfinished models, parallel CSS paths, and partially implemented features. Do not silently work around structural issues; call them out.

## Change Rules
- Prefer the smallest safe change that moves the product forward.
- Keep route files thin; move reusable logic into services, controllers, or Filament resources where appropriate.
- Preserve existing behavior unless the change is intentional and agreed.
- When touching styling, verify both app and admin because they currently use separate CSS entry points.
- When touching authorization or domain rules, add or update tests first where feasible.

## Practical Commands
- `composer setup` installs dependencies, prepares `.env`, migrates, and builds assets.
- `composer dev` runs Laravel, queue worker, logs, and Vite together.
- `php artisan test` runs the test suite.
- `vendor/bin/pint` formats PHP code.
- `npm run build` builds frontend assets.

## Review Focus
Prioritize findings around security, ownership boundaries, broken assumptions, CSS/font inconsistency, and incomplete business logic. The goal is not just to ship changes, but to steer GarageBook toward a reliable, maintainable product within 3 months.

## Session Status
Status bijgewerkt op 2026-05-04.

### Live SEO/content werk afgerond
- `/start` redirectt naar `/admin/register`; `/admin/register` is weer de echte Filament registratiepagina.
- `privacy-statement` en `algemene-voorwaarden` staan live op `noindex,nofollow`.
- Metadata live aangescherpt voor:
  - `/ons-verhaal`
  - `/contact`
  - `/universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026`
- Alle live blog-excerpts zijn aangescherpt voor betere snippets / AI-samenvattingen.
- Thematische interne linkstructuur voor blogs is in code verbeterd.

### Live/performance werk afgerond
- Homepage-afbeeldingen omgezet naar `webp` en gekoppeld.
- Publieke caching toegevoegd voor anonieme marketingpagina’s zonder querystring.
- `/blog-image/{path}` levert nu `webp`-varianten met sterke cache-headers waar mogelijk.
- Zware fallback-afbeelding in `resources/views/filament/widgets/my-vehicles.blade.php` omgezet naar `webp`.

### Relevante commits al gepusht
- `fd982dc` Improve public SEO templates and structured data
- `54bae43` Refine brand copy from onderhoudsboekje to onderhoudsboek
- `1ca3e55` Improve thematic internal content links
- `2113d8f` Optimize homepage images for performance
- `6ccc590` Add public caching and optimized blog images
- `e5ce14a` Optimize fallback vehicle widget image

### Belangrijk openstaand punt voor volgende sessie
- Diepere herschrijving van de rich-text body’s van de topblogs is nog niet veilig gelukt.
- Pogingen om live `data.content` via de Filament/Livewire editor op te slaan gaven `500` terug, terwijl excerpt- en metadata-updates wel werken.
- Volgende stap: eerst exact uitzoeken welk payloadformaat of welke save-route de rich-text editor accepteert, liefst via één gecontroleerde testwijziging, en pas daarna de blogbody’s inhoudelijk herschrijven.

### Lokale worktree-opmerking
- Er staan niet-gerelateerde lokale wijzigingen buiten dit werk:
  - verwijderde bestanden in `temp/...`
  - ongetrackte bestanden:
    - `app/Console/Commands/ExportNeverLoggedInUsersCommand.php`
    - `tests/Feature/ExportNeverLoggedInUsersCommandTest.php`
