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
