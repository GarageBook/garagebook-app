# Localization Workflow

Last updated: 2026-05-10

## Adding New Translation Keys

When adding a new user-facing string:

1. Choose the correct domain file in `lang/{locale}`.
2. Add the new key to `lang/nl/...` first using the exact Dutch source text.
3. Add the same key to `en`, `de`, and `fr`.
4. Replace the hardcoded UI text with `__('...')` or `trans_choice(...)`.
5. Verify the key appears in the Filament localization overview page.

## Naming Conventions

Use short, stable, file-scoped keys.

Recommended patterns:

- `dashboard.timeline_heading`
- `vehicles.fields.brand`
- `maintenance.actions.delete`
- `documents.table.expiry_date`
- `fuel.widget.trend_description`

Guidelines:

- use domain-first grouping
- use nested arrays for structure
- keep keys semantic, not presentational
- avoid reusing vague keys across unrelated domains

## When To Use `app.php`

Use `app.php` only for truly shared application-wide strings, for example:

- generic navigation groups
- localization admin page labels
- shared global UI terms that are not domain-specific

Use domain-specific files for everything else:

- `dashboard.php` for dashboard and timeline UI
- `vehicles.php` for vehicle resource UI
- `maintenance.php` for maintenance UI
- `documents.php` for document vault UI
- `fuel.php` for fuel / consumption UI
- `reminders.php` for reminder UI
- `emails.php` for mail subjects and mail view labels

## Adding New Locales Later

To add a new locale:

1. Add the locale to `config/locales.php`.
2. Create `lang/{locale}`.
3. Copy the current project translation files into that directory.
4. Fill every key for the new locale.
5. Verify key parity against the existing locales.
6. Check the Filament localization overview page for the new locale.

Do not enable a new locale publicly until content parity and UX rules are defined.

## Key Parity Checks

Key parity means each supported locale contains the same keys for each translation file.

Current practical checks:

- use the read-only Filament localization overview page to spot missing values
- compare flattened translation catalogs through `LocaleService`
- run localization-related tests

If parity becomes a recurring problem, add a dedicated automated parity test or CI script.

## Regression Testing

When localization changes are made:

- run `php artisan optimize:clear`
- run focused feature tests for the affected domain
- run localization support tests such as:
  - `LocalizationOverviewPageTest`
  - `LocaleServiceTest`
- run the full suite for cross-domain regressions when the scope is broad

Pluralization changes should also be checked with realistic counts in UI tests.

## Intentionally Not Translated

The following are intentionally left unchanged:

- routes
- slugs
- route names
- model properties
- database fields
- technical identifiers
- storage paths
- internal enum / action ids unless they are directly rendered to users

This keeps the rollout safe and avoids breaking behavior.

## Recommended Next Steps

- complete `auth.php`, `validation.php`, `pagination.php`, and `passwords.php` parity across all locales
- localize remaining users/admin resource UI where needed
- localize public/blog/marketing content only when multilingual public rollout is planned
- decide whether the unused welcome mail path should be removed or completed

## Possible Future Extensions

### Public locale switching

Possible later implementation:

- middleware or controller-based locale resolution
- explicit locale whitelist using `config/locales.php`
- UI switcher only after public content parity exists

### User locale preferences

Possible later implementation:

- store preferred locale on the user model
- resolve locale in `LocaleService::current(...)`
- apply locale after authentication, before rendering Filament or app views

### Localized routes

Possible later implementation:

- locale-prefixed public routes
- translated slugs or per-locale content mapping
- canonical and SEO strategy updates

This is intentionally out of scope for the current Dutch-first implementation.

